<?php

declare(strict_types=1);

namespace Modules\Transport\Services;

use App\Models\Organization;
use App\Services\OrganizationContextService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\BillingDocument;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryTransfer;
use Modules\Catalog\Enums\InventoryDocumentType;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Modules\Security\Models\SecurityBranch;
use Modules\Transport\Data\TransportGuideCommand;
use Modules\Transport\Data\TransportGuideItemData;
use Modules\Transport\Enums\TransportEnvironment;
use Modules\Transport\Enums\TransportGuideStatus;
use Modules\Transport\Enums\TransportGuideType;
use Modules\Transport\Enums\TransportMode;
use Modules\Transport\Jobs\IssueTransportGuideJob;
use Modules\Transport\Models\TransportGuide;
use Modules\Transport\Models\TransportGuideItem;
use Modules\Transport\Models\TransportGuideTransmission;
use Modules\Transport\Models\TransportSetting;

class TransportGuideService
{
    public function __construct(
        private readonly OrganizationContextService $organizationContext,
        private readonly OrganizationEntitlementService $entitlements,
        private readonly TransportGuideProviderResolver $providers,
    ) {}

    public function create(TransportGuideCommand $command): TransportGuide
    {
        $this->assertOrganization($command->organizationId);
        $this->assertIdempotencyKey($command->idempotencyKey);
        $setting = $this->setting($command->organizationId);
        $payload = $this->normalizeCommand($command, $setting);
        $hash = $this->hash($payload);
        $existing = $this->findByKey($command->organizationId, $command->idempotencyKey);
        if ($existing) {
            return $this->validateReplay($existing, $hash);
        }

        try {
            return DB::transaction(function () use ($command, $setting, $payload, $hash): TransportGuide {
                TransportSetting::query()->where('organization_id', $command->organizationId)->lockForUpdate()->firstOrFail();
                $existing = $this->findByKey($command->organizationId, $command->idempotencyKey, true);
                if ($existing) {
                    return $this->validateReplay($existing, $hash);
                }
                $this->validateReferences($command, $setting, $payload['items']);
                $series = $command->type === TransportGuideType::Sender ? $setting->sender_series : $setting->carrier_series;
                $number = $this->nextNumber($command->organizationId, $command->type, $series);
                $guide = TransportGuide::query()->create([
                    'organization_id' => $command->organizationId,
                    'branch_id' => $command->branchId,
                    'idempotency_key' => $command->idempotencyKey,
                    'payload_hash' => $hash,
                    'guide_type' => $command->type->value,
                    'series' => $series,
                    'number' => $number,
                    'status' => TransportGuideStatus::Ready->value,
                    'reason_code' => $command->reasonCode,
                    'reason_catalog_version' => (string) config('transport.catalog_20_version'),
                    'transport_mode' => $command->transportMode->value,
                    'issue_date' => now()->toDateString(),
                    'transfer_at' => $command->transferDate,
                    'origin_snapshot' => $payload['origin'],
                    'destination_snapshot' => $payload['destination'],
                    'recipient_snapshot' => $payload['recipient'],
                    'transport_snapshot' => $payload['transport'],
                    'gross_weight' => $payload['gross_weight'],
                    'weight_unit' => $payload['weight_unit'],
                    'package_count' => $payload['package_count'],
                    'inventory_document_id' => $command->inventoryDocumentId,
                    'inventory_transfer_id' => $command->inventoryTransferId,
                    'billing_document_id' => $command->billingDocumentId,
                    'related_guide_id' => $command->relatedGuideId,
                    'external_sender_snapshot' => $payload['external_sender'],
                    'exception_justification' => $payload['exception_justification'],
                    'provider' => $setting->provider,
                    'environment' => $setting->environment->value,
                    'provider_config_hash' => $setting->configurationFingerprint(),
                    'request_payload' => $payload,
                    'created_by' => $command->actorId,
                    'notes' => $command->notes,
                ]);
                foreach ($payload['items'] as $index => $item) {
                    TransportGuideItem::query()->create([
                        'organization_id' => $command->organizationId,
                        'transport_guide_id' => $guide->id,
                        'line_number' => $index + 1,
                        ...$item,
                    ]);
                }

                return $guide->load(['items.product', 'branch', 'relatedGuide', 'inventoryDocument', 'inventoryTransfer', 'billingDocument']);
            }, max(1, (int) config('transport.transaction_attempts', 5)));
        } catch (QueryException $exception) {
            $existing = $this->findByKey($command->organizationId, $command->idempotencyKey);
            if (! $existing) {
                throw $exception;
            }

            return $this->validateReplay($existing, $hash);
        }
    }

    public function enqueue(int $organizationId, int $guideId): TransportGuide
    {
        $this->assertOrganization($organizationId, false);
        $setting = $this->setting($organizationId);
        $this->assertProviderReady($setting);
        $shouldDispatch = false;
        $guide = DB::transaction(function () use ($organizationId, $guideId, $setting, &$shouldDispatch): TransportGuide {
            $guide = $this->lockGuide($organizationId, $guideId);
            $this->assertGuideConfiguration($setting, $guide);
            if ($guide->provider_ticket && $guide->status === TransportGuideStatus::Error) {
                $guide->forceFill(['status' => TransportGuideStatus::Submitted->value])->save();
                $this->recordTransmission($guide, 'recover_ticket', TransportGuideStatus::Error, TransportGuideStatus::Submitted, ['ticket_hash' => hash('sha256', (string) $guide->provider_ticket)], []);

                return $guide->fresh(['items', 'transmissions']);
            }
            if (in_array($guide->status, [TransportGuideStatus::Queued, TransportGuideStatus::Submitting, TransportGuideStatus::Submitted, TransportGuideStatus::Accepted, TransportGuideStatus::AcceptedWithObservation], true)) {
                return $guide;
            }
            if (! in_array($guide->status, [TransportGuideStatus::Ready, TransportGuideStatus::Error], true)) {
                throw ValidationException::withMessages(['guide' => 'La GRE no admite envio en su estado actual.']);
            }
            $before = $guide->status;
            $guide->forceFill(['status' => TransportGuideStatus::Queued->value, 'queued_at' => now()])->save();
            $this->recordTransmission($guide, 'enqueue', $before, TransportGuideStatus::Queued, ['payload_hash' => $guide->payload_hash], []);
            $shouldDispatch = true;

            return $guide->fresh(['items', 'transmissions']);
        });

        if ($shouldDispatch) {
            if ($setting->dispatch_mode === 'sync') {
                return $this->issue($organizationId, $guideId);
            }
            IssueTransportGuideJob::dispatch($organizationId, $guideId)
                ->onConnection($setting->queue_connection ?: null)
                ->onQueue($setting->queue_name ?: (string) config('transport.queue', 'transport'))
                ->afterCommit();
        }

        return $guide;
    }

    public function issue(int $organizationId, int $guideId): TransportGuide
    {
        $this->assertOrganization($organizationId, false);
        $setting = $this->setting($organizationId);
        $this->assertProviderReady($setting);
        $claimed = DB::transaction(function () use ($organizationId, $guideId, $setting): ?TransportGuide {
            $guide = $this->lockGuide($organizationId, $guideId);
            $this->assertGuideConfiguration($setting, $guide);
            if (in_array($guide->status, [TransportGuideStatus::Submitted, TransportGuideStatus::Accepted, TransportGuideStatus::AcceptedWithObservation], true)) {
                return null;
            }
            if ($guide->status !== TransportGuideStatus::Queued) {
                throw ValidationException::withMessages(['guide' => 'Solo una GRE encolada puede enviarse.']);
            }
            $guide->forceFill(['status' => TransportGuideStatus::Submitting->value])->save();

            return $guide->fresh(['items', 'relatedGuide', 'billingDocument']);
        });
        if (! $claimed) {
            return $this->guide($organizationId, $guideId);
        }

        $provider = $this->providers->resolve((string) $setting->provider);
        $result = $provider->submit($setting, $claimed);

        return DB::transaction(function () use ($organizationId, $guideId, $result): TransportGuide {
            $guide = $this->lockGuide($organizationId, $guideId);
            if ($guide->status !== TransportGuideStatus::Submitting) {
                return $guide;
            }
            $next = TransportGuideStatus::tryFrom((string) ($result['status'] ?? 'error')) ?? TransportGuideStatus::Error;
            $files = $this->persistProviderFiles($guide, $result);
            $guide->forceFill([
                'status' => $next->value,
                'provider_ticket' => $result['ticket'] ?? $guide->provider_ticket,
                'provider_code' => $result['provider_code'] ?? null,
                'provider_description' => $result['provider_description'] ?? $result['message'] ?? null,
                'response_payload' => Arr::except($result, ['xml', 'cdr']),
                'submitted_at' => $next === TransportGuideStatus::Submitted ? now() : null,
                ...$files,
            ])->save();
            $this->recordTransmission($guide, 'submit', TransportGuideStatus::Submitting, $next, ['payload_hash' => $guide->payload_hash], Arr::except($result, ['xml', 'cdr']));

            return $guide->fresh(['items', 'transmissions']);
        });
    }

    public function poll(int $organizationId, int $guideId): TransportGuide
    {
        $this->assertOrganization($organizationId, false);
        $setting = $this->setting($organizationId);
        $this->assertProviderReady($setting);
        $guide = $this->guide($organizationId, $guideId);
        $this->assertGuideConfiguration($setting, $guide);
        if (in_array($guide->status, [TransportGuideStatus::Accepted, TransportGuideStatus::AcceptedWithObservation, TransportGuideStatus::Rejected], true)) {
            return $guide;
        }
        if ($guide->status !== TransportGuideStatus::Submitted || ! $guide->provider_ticket) {
            throw ValidationException::withMessages(['guide' => 'La GRE no tiene un ticket pendiente de consulta.']);
        }
        $result = $this->providers->resolve((string) $setting->provider)->poll($setting, $guide);

        return DB::transaction(function () use ($organizationId, $guideId, $result): TransportGuide {
            $guide = $this->lockGuide($organizationId, $guideId);
            if ($guide->status !== TransportGuideStatus::Submitted) {
                return $guide;
            }
            $next = TransportGuideStatus::tryFrom((string) ($result['status'] ?? 'submitted')) ?? TransportGuideStatus::Submitted;
            if ($next === TransportGuideStatus::Error) {
                $next = TransportGuideStatus::Submitted;
            }
            $files = $this->persistProviderFiles($guide, $result);
            $guide->forceFill([
                'status' => $next->value,
                'provider_code' => $result['provider_code'] ?? null,
                'provider_description' => $result['provider_description'] ?? $result['message'] ?? null,
                'response_payload' => Arr::except($result, ['xml', 'cdr']),
                'accepted_at' => in_array($next, [TransportGuideStatus::Accepted, TransportGuideStatus::AcceptedWithObservation], true) ? now() : null,
                'rejected_at' => $next === TransportGuideStatus::Rejected ? now() : null,
                ...$files,
            ])->save();
            $this->recordTransmission($guide, 'poll', TransportGuideStatus::Submitted, $next, ['ticket' => $guide->provider_ticket], Arr::except($result, ['xml', 'cdr']));

            return $guide->fresh(['items', 'transmissions']);
        });
    }

    public function markSubmissionUncertain(int $organizationId, int $guideId, string $reason): TransportGuide
    {
        return DB::transaction(function () use ($organizationId, $guideId, $reason): TransportGuide {
            $guide = $this->lockGuide($organizationId, $guideId);
            if ($guide->status !== TransportGuideStatus::Submitting) {
                return $guide;
            }
            $guide->forceFill([
                'status' => TransportGuideStatus::Uncertain->value,
                'provider_code' => 'WORKER_FAILED',
                'provider_description' => 'Resultado externo incierto; requiere conciliacion antes de cualquier reenvio.',
            ])->save();
            $this->recordTransmission(
                $guide,
                'submission_uncertain',
                TransportGuideStatus::Submitting,
                TransportGuideStatus::Uncertain,
                ['payload_hash' => $guide->payload_hash],
                ['reason_hash' => hash('sha256', $reason)],
            );

            return $guide->fresh(['items', 'transmissions']);
        });
    }

    public function validateCredentials(TransportSetting $setting): TransportSetting
    {
        $this->assertOrganization((int) $setting->organization_id, false);
        $result = $this->providers->resolve((string) $setting->provider)->validateCredentials($setting);
        if (! ($result['ok'] ?? false)) {
            throw ValidationException::withMessages(['credentials' => (string) ($result['message'] ?? 'Credenciales GRE invalidas.')]);
        }
        $hash = hash('sha256', json_encode($setting->provider_credentials ?? [], JSON_THROW_ON_ERROR));
        $setting->forceFill(['credentials_hash' => $hash, 'credentials_validated_at' => now()])->save();

        return $setting->fresh();
    }

    private function setting(int $organizationId): TransportSetting
    {
        $setting = TransportSetting::query()->where('organization_id', $organizationId)->first();
        if (! $setting || ! $setting->enabled) {
            throw ValidationException::withMessages(['transport' => 'El modulo de transporte no esta habilitado para la organizacion.']);
        }

        return $setting;
    }

    private function assertProviderReady(TransportSetting $setting): void
    {
        if ($setting->environment === TransportEnvironment::Simulation && $setting->provider !== 'simulation') {
            throw ValidationException::withMessages(['provider' => 'El entorno simulacion debe usar el proveedor simulado.']);
        }
        if ($setting->environment === TransportEnvironment::Production && ($setting->provider !== 'greenter' || ! $setting->productionCredentialsAreValid())) {
            throw ValidationException::withMessages(['credentials' => 'Produccion GRE requiere proveedor Greenter y credenciales validadas sin cambios.']);
        }
        $connection = $setting->queue_connection ?: config('queue.default');
        if ($setting->environment === TransportEnvironment::Production && ($setting->dispatch_mode !== 'queue' || $connection === 'sync')) {
            throw ValidationException::withMessages(['queue' => 'Produccion GRE requiere una conexion de cola asincrona.']);
        }
    }

    private function assertGuideConfiguration(TransportSetting $setting, TransportGuide $guide): void
    {
        if ($guide->provider !== $setting->provider
            || $guide->environment !== $setting->environment
            || ! hash_equals((string) $guide->provider_config_hash, $setting->configurationFingerprint())) {
            throw ValidationException::withMessages(['configuration' => 'La configuracion GRE cambio despues de preparar la guia. Emite una nueva guia con la configuracion vigente.']);
        }
    }

    /** @return array<string, mixed> */
    private function normalizeCommand(TransportGuideCommand $command, TransportSetting $setting): array
    {
        if (! array_key_exists($command->reasonCode, config('transport.reasons', []))) {
            throw ValidationException::withMessages(['reason_code' => 'Motivo de traslado no incluido en el Catalogo 20 vigente.']);
        }
        if ($command->grossWeight <= 0 || strtoupper($command->weightUnit) !== 'KGM') {
            throw ValidationException::withMessages(['gross_weight' => 'El peso bruto debe ser positivo y expresarse en KGM.']);
        }
        if ($command->transferDate < new \DateTimeImmutable('today')) {
            throw ValidationException::withMessages(['transfer_at' => 'El inicio del traslado no puede ser anterior a la fecha de emision.']);
        }
        $origin = $this->normalizeLocation($command->origin, 'origin');
        $destination = $this->normalizeLocation($command->destination, 'destination');
        if ($origin['ubigeo'] === $destination['ubigeo'] && $origin['address'] === $destination['address']) {
            throw ValidationException::withMessages(['destination' => 'La GRE requiere un punto de llegada distinto al punto de partida.']);
        }
        $recipient = $this->requiredStrings($command->recipient, ['document_type', 'document_number', 'name'], 'recipient');
        $transport = $command->transportMode === TransportMode::Public
            ? $this->requiredStrings($command->transport, ['carrier_document_number', 'carrier_name'], 'transport')
            : $this->requiredStrings($command->transport, ['vehicle_plate', 'driver_document_number', 'driver_name', 'driver_license'], 'transport');
        if ($command->type === TransportGuideType::Carrier && $command->transportMode !== TransportMode::Public) {
            throw ValidationException::withMessages(['guide_type' => 'La GRE transportista corresponde a transporte publico.']);
        }
        if ($command->relatedGuideId && $command->externalSender) {
            throw ValidationException::withMessages(['external_sender' => 'Selecciona una GRE remitente interna o una referencia externa, no ambas.']);
        }
        $externalSender = null;
        if ($command->externalSender) {
            if ($command->type !== TransportGuideType::Carrier) {
                throw ValidationException::withMessages(['external_sender' => 'La referencia GRE externa solo aplica a la guia transportista.']);
            }
            $externalSender = $this->requiredStrings($command->externalSender, ['document_type', 'number', 'issuer_ruc'], 'external_sender');
            if ($externalSender['document_type'] !== '09' || preg_match('/^T[A-Z0-9]{3}-\d{1,8}$/', $externalSender['number']) !== 1 || preg_match('/^\d{11}$/', $externalSender['issuer_ruc']) !== 1) {
                throw ValidationException::withMessages(['external_sender' => 'La GRE remitente externa requiere tipo 09, numero Txxx-correlativo y RUC emisor valido.']);
            }
        }
        if ($command->type === TransportGuideType::Carrier && ! $command->relatedGuideId && ! $externalSender) {
            if (! $setting->allow_carrier_without_sender || mb_strlen(trim((string) $command->exceptionJustification)) < 10) {
                throw ValidationException::withMessages(['related_guide_id' => 'La GRE transportista requiere GRE remitente aceptada o una excepcion configurada y justificada.']);
            }
        }
        if ($command->items === []) {
            throw ValidationException::withMessages(['items' => 'La GRE requiere al menos un bien trasladado.']);
        }
        $items = array_map(function (TransportGuideItemData $item): array {
            $description = trim($item->description);
            $code = trim($item->code);
            $unit = strtoupper(trim($item->unitCode));
            if ($description === '' || $code === '' || $unit === '' || $item->quantity <= 0) {
                throw ValidationException::withMessages(['items' => 'Cada item requiere codigo, descripcion, unidad y cantidad positiva.']);
            }

            return [
                'product_id' => $item->productId,
                'code' => $code,
                'description' => $description,
                'quantity' => round($item->quantity, 4),
                'unit_code' => $unit,
                'sunat_product_code' => $item->sunatProductCode,
            ];
        }, $command->items);

        return [
            'organization_id' => $command->organizationId,
            'branch_id' => $command->branchId,
            'type' => $command->type->value,
            'reason_code' => $command->reasonCode,
            'reason_catalog_version' => (string) config('transport.catalog_20_version'),
            'transport_mode' => $command->transportMode->value,
            'transfer_at' => $command->transferDate->format(DATE_ATOM),
            'origin' => $origin,
            'destination' => $destination,
            'recipient' => $recipient,
            'transport' => $transport,
            'gross_weight' => round($command->grossWeight, 3),
            'weight_unit' => strtoupper($command->weightUnit),
            'package_count' => $command->packageCount,
            'inventory_document_id' => $command->inventoryDocumentId,
            'inventory_transfer_id' => $command->inventoryTransferId,
            'billing_document_id' => $command->billingDocumentId,
            'related_guide_id' => $command->relatedGuideId,
            'external_sender' => $externalSender,
            'exception_justification' => trim((string) $command->exceptionJustification) ?: null,
            'items' => $items,
            'notes' => $command->notes,
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function validateReferences(TransportGuideCommand $command, TransportSetting $setting, array $items): void
    {
        $branch = SecurityBranch::query()->where('organization_id', $command->organizationId)->whereKey($command->branchId)->where('is_active', true)->first();
        if (! $branch) {
            throw ValidationException::withMessages(['branch_id' => 'La sucursal de la GRE no pertenece a la organizacion o esta inactiva.']);
        }
        $productIds = array_values(array_filter(array_map(fn (array $item): ?int => $item['product_id'], $items)));
        if ($productIds !== [] && DB::table('products')->where('organization_id', $command->organizationId)->whereIn('id', $productIds)->count() !== count(array_unique($productIds))) {
            throw ValidationException::withMessages(['items' => 'Uno de los productos no pertenece a la organizacion.']);
        }
        if ($command->inventoryDocumentId && $command->inventoryTransferId) {
            throw ValidationException::withMessages(['operation' => 'Una GRE no puede vincular simultaneamente documento de inventario y transferencia.']);
        }
        if ($command->inventoryDocumentId) {
            $document = InventoryDocument::query()->where('organization_id', $command->organizationId)->with('items')->find($command->inventoryDocumentId);
            if (! $document || (int) $document->branch_id !== $command->branchId || ! in_array($document->document_type, [InventoryDocumentType::Dispatch, InventoryDocumentType::CustomerReturn, InventoryDocumentType::SupplierReturn], true)) {
                throw ValidationException::withMessages(['inventory_document_id' => 'La operacion interna no es un despacho o devolucion valida.']);
            }
            $this->assertItemsMatch($items, $document->items->map(fn ($item) => ['product_id' => (int) $item->product_id, 'quantity' => (float) $item->quantity])->all(), 'documento de inventario');
        }
        if ($command->inventoryTransferId) {
            $transfer = InventoryTransfer::query()->where('organization_id', $command->organizationId)->with('items')->find($command->inventoryTransferId);
            if (! $transfer || (int) $transfer->source_branch_id !== $command->branchId) {
                throw ValidationException::withMessages(['inventory_transfer_id' => 'La transferencia no pertenece al origen de la GRE.']);
            }
            if ($command->reasonCode !== '04') {
                throw ValidationException::withMessages(['reason_code' => 'Una transferencia interna requiere motivo 04.']);
            }
            $this->assertItemsMatch($items, $transfer->items->map(fn ($item) => ['product_id' => (int) $item->product_id, 'quantity' => (float) $item->quantity])->all(), 'transferencia');
        }
        if ($command->billingDocumentId && ! BillingDocument::query()->where('organization_id', $command->organizationId)->where('branch_id', $command->branchId)->where('status', 'issued')->whereKey($command->billingDocumentId)->exists()) {
            throw ValidationException::withMessages(['billing_document_id' => 'El comprobante relacionado debe pertenecer a la sucursal y estar emitido.']);
        }
        if ($command->type === TransportGuideType::Carrier && $command->relatedGuideId) {
            $sender = TransportGuide::query()->where('organization_id', $command->organizationId)->find($command->relatedGuideId);
            if (! $sender || (int) $sender->branch_id !== $command->branchId || $sender->guide_type !== TransportGuideType::Sender || ! in_array($sender->status, [TransportGuideStatus::Accepted, TransportGuideStatus::AcceptedWithObservation], true)) {
                throw ValidationException::withMessages(['related_guide_id' => 'La GRE transportista requiere una GRE remitente aceptada del mismo tenant.']);
            }
        }
    }

    /** @param array<int, array<string, mixed>> $actual @param array<int, array<string, mixed>> $expected */
    private function assertItemsMatch(array $actual, array $expected, string $source): void
    {
        $map = fn (array $rows): array => collect($rows)->groupBy('product_id')->map(fn ($group): float => round((float) $group->sum('quantity'), 4))->sortKeys()->all();
        if ($map($actual) !== $map($expected)) {
            throw ValidationException::withMessages(['items' => "Los bienes de la GRE no coinciden con el {$source} vinculado."]);
        }
    }

    private function nextNumber(int $organizationId, TransportGuideType $type, string $series): int
    {
        DB::table('transport_guide_counters')->insertOrIgnore([
            'organization_id' => $organizationId, 'guide_type' => $type->value, 'series' => $series,
            'next_number' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $counter = DB::table('transport_guide_counters')
            ->where('organization_id', $organizationId)->where('guide_type', $type->value)->where('series', $series)
            ->lockForUpdate()->first();
        $number = (int) $counter->next_number;
        DB::table('transport_guide_counters')
            ->where('organization_id', $organizationId)->where('guide_type', $type->value)->where('series', $series)
            ->update(['next_number' => $number + 1, 'updated_at' => now()]);

        return $number;
    }

    /** @return array<string, mixed> */
    private function persistProviderFiles(TransportGuide $guide, array $result): array
    {
        $files = [];
        foreach (['xml' => 'xml', 'cdr' => 'zip'] as $key => $extension) {
            $content = $result[$key] ?? null;
            if (! is_string($content) || $content === '') {
                continue;
            }
            $path = 'transport/'.$guide->organization_id.'/'.$key.'/'.now()->format('Ym').'/'.$guide->formattedNumber().'.'.$extension;
            Storage::disk('local')->put($path, $content);
            $files[$key.'_disk'] = 'local';
            $files[$key.'_path'] = $path;
            $files[$key.'_hash'] = hash('sha256', $content);
        }

        return $files;
    }

    private function recordTransmission(TransportGuide $guide, string $operation, ?TransportGuideStatus $before, TransportGuideStatus $after, array $request, array $response, ?string $key = null): void
    {
        $attempt = TransportGuideTransmission::query()->where('organization_id', $guide->organization_id)->where('transport_guide_id', $guide->id)->count() + 1;
        TransportGuideTransmission::query()->create([
            'organization_id' => $guide->organization_id,
            'transport_guide_id' => $guide->id,
            'idempotency_key' => $key ?? 'guide:'.$guide->id.':'.$operation.':'.$attempt,
            'operation' => $operation,
            'status_before' => $before?->value,
            'status_after' => $after->value,
            'attempt_number' => $attempt,
            'request_payload' => $request,
            'response_payload' => $response,
            'occurred_at' => now(),
        ]);
    }

    private function guide(int $organizationId, int $guideId): TransportGuide
    {
        return TransportGuide::query()->where('organization_id', $organizationId)->with(['items', 'transmissions'])->findOrFail($guideId);
    }

    private function lockGuide(int $organizationId, int $guideId): TransportGuide
    {
        return TransportGuide::query()->where('organization_id', $organizationId)->lockForUpdate()->findOrFail($guideId);
    }

    private function findByKey(int $organizationId, string $key, bool $lock = false): ?TransportGuide
    {
        $query = TransportGuide::query()->where('organization_id', $organizationId)->where('idempotency_key', $key);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function validateReplay(TransportGuide $guide, string $hash): TransportGuide
    {
        if (! hash_equals((string) $guide->payload_hash, $hash)) {
            throw ValidationException::withMessages(['idempotency_key' => 'La clave GRE ya fue usada con otro traslado.']);
        }

        return $guide->load(['items', 'transmissions']);
    }

    private function assertOrganization(int $organizationId, bool $requireContext = true): void
    {
        $context = $this->organizationContext->currentOrganizationId();
        if ($requireContext && $context && (int) $context !== $organizationId) {
            throw ValidationException::withMessages(['organization_id' => 'La organizacion no coincide con el contexto activo.']);
        }
        $organization = Organization::query()->find($organizationId);
        if (! $organization || ! $organization->isActiveStatus()) {
            throw ValidationException::withMessages(['organization_id' => 'La organizacion no esta operativa.']);
        }
        if (! $this->entitlements->hasCapability('transport.gre', $organization)) {
            throw ValidationException::withMessages(['plan' => 'La organizacion no tiene habilitada la capacidad transport.gre.']);
        }
    }

    private function assertIdempotencyKey(string $key): void
    {
        if (trim($key) === '' || mb_strlen($key) > 160) {
            throw ValidationException::withMessages(['idempotency_key' => 'La clave idempotente GRE es obligatoria y no puede superar 160 caracteres.']);
        }
    }

    /** @param array<string, mixed> $location @return array<string, mixed> */
    private function normalizeLocation(array $location, string $field): array
    {
        $normalized = $this->requiredStrings($location, ['ubigeo', 'address'], $field);
        if (preg_match('/^\d{6}$/', (string) $normalized['ubigeo']) !== 1) {
            throw ValidationException::withMessages([$field.'.ubigeo' => 'El ubigeo debe contener seis digitos.']);
        }
        $normalized['establishment_code'] = trim((string) ($location['establishment_code'] ?? '0000')) ?: '0000';
        if (isset($location['ruc'])) {
            $normalized['ruc'] = trim((string) $location['ruc']);
        }

        return $normalized;
    }

    /** @param array<string, mixed> $data @param array<int, string> $keys @return array<string, mixed> */
    private function requiredStrings(array $data, array $keys, string $field): array
    {
        $result = $data;
        foreach ($keys as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value === '') {
                throw ValidationException::withMessages([$field.'.'.$key => "El campo {$field}.{$key} es obligatorio."]);
            }
            $result[$key] = $value;
        }

        return $result;
    }

    private function hash(array $payload): string
    {
        return hash('sha256', json_encode(Arr::sortRecursive($payload), JSON_THROW_ON_ERROR));
    }
}

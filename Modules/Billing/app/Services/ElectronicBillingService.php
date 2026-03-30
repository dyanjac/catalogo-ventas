<?php

namespace Modules\Billing\Services;

use App\Services\OrganizationContextService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Modules\Billing\Jobs\IssueBillingDocumentJob;
use Modules\Billing\Models\BillingDocument;
use Modules\Billing\Models\BillingDocumentFile;
use Modules\Billing\Models\BillingDocumentResponseHistory;
use Modules\Billing\Models\BillingSetting;
use Modules\Billing\Services\Xml\BillingXmlGenerator;
use Throwable;

class ElectronicBillingService
{
    public function __construct(
        private readonly BillingProviderResolver $resolver,
        private readonly BillingXmlGenerator $xmlGenerator,
        private readonly OrganizationContextService $organizationContext
    )
    {
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function issueOrQueue(BillingDocument $document, array $payload): array
    {
        $setting = $this->resolveSetting();

        if ($blocked = $this->guardSuspendedTenant($document, $setting, $payload, 'tenant_suspended_queue_blocked')) {
            return $blocked;
        }

        if (! $setting || ! $setting->enabled) {
            return [
                'ok' => false,
                'queued' => false,
                'message' => 'La facturación electrónica está desactivada.',
            ];
        }

        if ($setting->dispatch_mode !== 'queue') {
            $payload = $this->withDispatchMeta($payload, 'sync', null, null, false);
            $result = $this->issue($document, $payload);
            $result['queued'] = false;

            return $result;
        }

        $connection = is_string($setting->queue_connection) && trim($setting->queue_connection) !== ''
            ? trim($setting->queue_connection)
            : (string) config('queue.default');
        $queueName = is_string($setting->queue_name) && trim($setting->queue_name) !== ''
            ? trim($setting->queue_name)
            : 'default';

        $payload = $this->withDispatchMeta($payload, 'queue', $connection, $queueName, true);

        $job = new IssueBillingDocumentJob($document->id, $payload);
        if (is_string($setting->queue_connection) && trim($setting->queue_connection) !== '') {
            $job->onConnection(trim($setting->queue_connection));
        }
        if (is_string($setting->queue_name) && trim($setting->queue_name) !== '') {
            $job->onQueue(trim($setting->queue_name));
        }

        Bus::dispatch($job);

        $document->update([
            'provider' => $setting->provider,
            'request_payload' => $payload,
            'status' => 'queued',
            'issued_at' => null,
        ]);

        $this->storeQueueDispatchedHistory($document, $setting, $payload, $connection, $queueName);

        return [
            'ok' => true,
            'queued' => true,
            'message' => 'Comprobante encolado para emisión electrónica.',
            'connection' => $connection,
            'queue' => $queueName,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function issue(BillingDocument $document, array $payload): array
    {
        $setting = $this->resolveSetting();

        if ($blocked = $this->guardSuspendedTenant($document, $setting, $payload, 'tenant_suspended_issue_blocked')) {
            return $blocked;
        }

        if (! $setting || ! $setting->enabled) {
            return [
                'ok' => false,
                'message' => 'La facturación electrónica está desactivada.',
            ];
        }

        $fallbackXmlPath = $this->xmlGenerator->generate($document, $payload);
        $fallbackXmlHash = hash('sha256', Storage::disk('public')->get($fallbackXmlPath));
        $payload['xml_path'] = $fallbackXmlPath;
        $payload['xml_hash'] = $fallbackXmlHash;

        $provider = $this->resolver->resolveFromSetting($setting);
        try {
            $result = $provider->issueDocument($setting, $payload);
        } catch (Throwable $e) {
            $result = [
                'ok' => false,
                'message' => 'Excepción durante la emisión: '.$e->getMessage(),
            ];

            $this->storeResponseHistory($document, $setting, $payload, $result, $e);
            $document->update([
                'provider' => $setting->provider,
                'request_payload' => $payload,
                'response_payload' => $result,
                'status' => 'error',
                'issued_at' => null,
            ]);

            return $result;
        }

        $this->persistXmlFromResult($document, $payload, $result);

        $this->storeResponseHistory($document, $setting, $payload, $result);

        $document->update([
            'provider' => $setting->provider,
            'request_payload' => $payload,
            'response_payload' => $result,
            'status' => (bool) ($result['ok'] ?? false) ? 'issued' : 'error',
            'issued_at' => (bool) ($result['ok'] ?? false) ? now() : null,
            'sunat_ticket' => data_get($result, 'ticket') ?? data_get($result, 'sunat_ticket'),
            'sunat_cdr_code' => data_get($result, 'sunat_cdr_code') ?? data_get($result, 'cdr_code'),
            'sunat_cdr_description' => data_get($result, 'sunat_cdr_description') ?? data_get($result, 'cdr_description'),
        ]);

        $this->persistCdrFromResult($document, $result);

        return $result;
    }

    /**
     * @param array<string,mixed> $requestPayload
     * @param array<string,mixed> $result
     */
    private function persistXmlFromResult(BillingDocument $document, array $requestPayload, array $result): void
    {
        $providerXmlPath = data_get($result, 'xml_path') ?? data_get($result, 'body.xml_path');
        $providerXmlHash = data_get($result, 'xml_hash') ?? data_get($result, 'body.xml_hash');
        $providerXmlSource = data_get($result, 'xml_source') ?? 'provider-path';

        if (is_string($providerXmlPath) && $providerXmlPath !== '' && Storage::disk('public')->exists($providerXmlPath)) {
            $content = Storage::disk('public')->get($providerXmlPath);
            $hash = is_string($providerXmlHash) && $providerXmlHash !== ''
                ? $providerXmlHash
                : hash('sha256', $content);

            $this->storeFileRecord($document, 'xml', [
                'storage_disk' => 'public',
                'storage_path' => $providerXmlPath,
                'mime_type' => 'application/xml',
                'size' => Storage::disk('public')->size($providerXmlPath),
                'hash_sha256' => $hash,
                'metadata' => [
                    'source' => (string) $providerXmlSource,
                ],
            ]);

            $document->update([
                'xml_path' => $providerXmlPath,
                'xml_hash' => $hash,
            ]);

            return;
        }

        $fallbackXmlPath = data_get($requestPayload, 'xml_path');
        if (! is_string($fallbackXmlPath) || $fallbackXmlPath === '' || ! Storage::disk('public')->exists($fallbackXmlPath)) {
            return;
        }

        $fallbackHash = data_get($requestPayload, 'xml_hash');
        $content = Storage::disk('public')->get($fallbackXmlPath);
        $hash = is_string($fallbackHash) && $fallbackHash !== ''
            ? $fallbackHash
            : hash('sha256', $content);

        $this->storeFileRecord($document, 'xml', [
            'storage_disk' => 'public',
            'storage_path' => $fallbackXmlPath,
            'mime_type' => 'application/xml',
            'size' => Storage::disk('public')->size($fallbackXmlPath),
            'hash_sha256' => $hash,
            'metadata' => [
                'source' => 'xml-generator',
            ],
        ]);

        $document->update([
            'xml_path' => $fallbackXmlPath,
            'xml_hash' => $hash,
        ]);
    }

    /**
     * @param array<string,mixed> $result
     */
    private function persistCdrFromResult(BillingDocument $document, array $result): void
    {
        $path = data_get($result, 'cdr_path') ?? data_get($result, 'body.cdr_path');
        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            $this->storeFileRecord($document, 'cdr', [
                'storage_disk' => 'public',
                'storage_path' => $path,
                'mime_type' => 'application/xml',
                'size' => Storage::disk('public')->size($path),
                'hash_sha256' => hash('sha256', Storage::disk('public')->get($path)),
                'metadata' => ['source' => 'provider-path'],
            ]);

            return;
        }

        $base64 = data_get($result, 'cdr_base64')
            ?? data_get($result, 'body.cdr_base64')
            ?? data_get($result, 'body.cdrZipBase64');

        if (is_string($base64) && $base64 !== '') {
            $decoded = base64_decode($base64, true);
            if ($decoded !== false) {
                $dir = 'billing/cdr/' . now()->format('Ym');
                $path = $dir . '/R-' . $document->series . '-' . $document->number . '.zip';
                Storage::disk('public')->put($path, $decoded);

                $this->storeFileRecord($document, 'cdr', [
                    'storage_disk' => 'public',
                    'storage_path' => $path,
                    'mime_type' => 'application/zip',
                    'size' => Storage::disk('public')->size($path),
                    'hash_sha256' => hash('sha256', $decoded),
                    'metadata' => ['source' => 'provider-base64'],
                ]);

                return;
            }
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function storeFileRecord(BillingDocument $document, string $fileType, array $data): void
    {
        BillingDocumentFile::query()->updateOrCreate(
            [
                'billing_document_id' => $document->id,
                'file_type' => $fileType,
            ],
            [
                'storage_disk' => (string) ($data['storage_disk'] ?? 'public'),
                'storage_path' => (string) ($data['storage_path'] ?? ''),
                'mime_type' => $data['mime_type'] ?? null,
                'size' => $data['size'] ?? null,
                'hash_sha256' => $data['hash_sha256'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]
        );
    }

    /**
     * @param array<string,mixed> $requestPayload
     * @param array<string,mixed> $responsePayload
     */
    private function storeResponseHistory(
        BillingDocument $document,
        BillingSetting $setting,
        array $requestPayload,
        array $responsePayload,
        ?Throwable $exception = null,
        ?string $event = null
    ): void {
        $dispatchMode = (string) data_get($requestPayload, '_dispatch.mode', 'sync');
        $resolvedEvent = $event ?: $this->resolveIssueEvent($dispatchMode);

        BillingDocumentResponseHistory::query()->create([
            'billing_document_id' => $document->id,
            'provider' => $setting->provider,
            'environment' => $setting->environment,
            'event' => $resolvedEvent,
            'ok' => (bool) ($responsePayload['ok'] ?? false),
            'status_code' => isset($responsePayload['status_code']) ? (int) $responsePayload['status_code'] : null,
            'message' => (string) ($responsePayload['message'] ?? ''),
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'error_class' => $exception ? $exception::class : null,
            'error_message' => $exception?->getMessage(),
        ]);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function withDispatchMeta(
        array $payload,
        string $mode,
        ?string $connection,
        ?string $queue,
        bool $queued
    ): array {
        $payload['_dispatch'] = [
            'mode' => $mode,
            'connection' => $connection,
            'queue' => $queue,
            'queued' => $queued,
            'at' => now()->toDateTimeString(),
        ];

        return $payload;
    }

    private function resolveIssueEvent(string $dispatchMode): string
    {
        return $dispatchMode === 'queue' ? 'issue_queue' : 'issue_sync';
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function storeQueueDispatchedHistory(
        BillingDocument $document,
        BillingSetting $setting,
        array $payload,
        string $connection,
        string $queueName
    ): void {
        BillingDocumentResponseHistory::query()->create([
            'billing_document_id' => $document->id,
            'provider' => $setting->provider,
            'environment' => $setting->environment,
            'event' => 'queue_dispatched',
            'ok' => true,
            'status_code' => null,
            'message' => "Comprobante encolado para emisión (connection={$connection}, queue={$queueName}).",
            'request_payload' => $payload,
            'response_payload' => [
                'ok' => true,
                'queued' => true,
                'dispatch' => [
                    'mode' => 'queue',
                    'connection' => $connection,
                    'queue' => $queueName,
                ],
            ],
            'error_class' => null,
            'error_message' => null,
        ]);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    private function guardSuspendedTenant(BillingDocument $document, ?BillingSetting $setting, array $payload, string $event): ?array
    {
        $organization = $document->organization()->first() ?? $this->organizationContext->current();

        if (! $organization?->isSuspended()) {
            return null;
        }

        $result = [
            'ok' => false,
            'queued' => false,
            'message' => 'La organización asociada al comprobante está suspendida. La emisión fue bloqueada.',
        ];

        $document->update([
            'provider' => $setting?->provider ?? $document->provider,
            'request_payload' => $payload,
            'response_payload' => $result,
            'status' => 'error',
            'issued_at' => null,
        ]);

        if ($setting) {
            $this->storeResponseHistory($document, $setting, $payload, $result, null, $event);
        }

        return $result;
    }

    private function resolveSetting(): ?BillingSetting
    {
        $query = BillingSetting::query();

        if (! \Illuminate\Support\Facades\Schema::hasColumn('billing_settings', 'organization_id')) {
            return $query->first();
        }

        $organizationId = $this->organizationContext->currentOrganizationId();

        if ($organizationId) {
            return $query->where('organization_id', $organizationId)->first()
                ?? BillingSetting::query()->whereNull('organization_id')->first()
                ?? BillingSetting::query()->first();
        }

        return $query->whereNull('organization_id')->first() ?? $query->first();
    }
}

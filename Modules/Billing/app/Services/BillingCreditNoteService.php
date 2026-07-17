<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Services\EconomicEventService;
use Modules\Billing\Models\BillingDocument;

class BillingCreditNoteService
{
    public function __construct(private readonly EconomicEventService $economicEvents) {}

    /** @param array<string, mixed> $data */
    public function create(BillingDocument $original, array $data): BillingDocument
    {
        if ($original->status !== 'issued' || ! in_array($original->document_type, ['factura', 'boleta'], true)) {
            throw ValidationException::withMessages(['document' => 'La nota de credito requiere una factura o boleta emitida.']);
        }

        $key = trim((string) ($data['idempotency_key'] ?? ''));
        if ($key === '' || mb_strlen($key) > 160) {
            throw ValidationException::withMessages(['idempotency_key' => 'La clave idempotente es obligatoria.']);
        }
        $reasonCode = trim((string) ($data['reason_code'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reasonCode === '' || $reason === '') {
            throw ValidationException::withMessages(['reason' => 'La nota de credito requiere codigo y motivo.']);
        }
        $series = strtoupper(trim((string) ($data['series'] ?? 'FC01')));
        $number = trim((string) ($data['number'] ?? ''));
        if ($series === '' || $number === '') {
            throw ValidationException::withMessages(['number' => 'La serie y el numero de la nota de credito son obligatorios.']);
        }

        $hash = hash('sha256', json_encode(Arr::sortRecursive([
            'organization_id' => (int) $original->organization_id,
            'original_id' => (int) $original->id,
            'series' => $series,
            'number' => $number,
            'reason_code' => $reasonCode,
            'reason' => $reason,
            'total' => (float) ($data['total'] ?? $original->total),
        ]), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($original, $data, $key, $reasonCode, $reason, $series, $number, $hash): BillingDocument {
            $lockedOriginal = BillingDocument::query()
                ->where('organization_id', $original->organization_id)
                ->lockForUpdate()
                ->findOrFail($original->id);
            $existing = BillingDocument::query()
                ->where('organization_id', $original->organization_id)
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                if (! hash_equals((string) $existing->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'La clave ya fue usada con otra nota de credito.']);
                }

                return $existing;
            }

            $total = round((float) ($data['total'] ?? $original->total), 2);
            if ($total <= 0 || $total > (float) $original->total) {
                throw ValidationException::withMessages(['total' => 'El total de la nota de credito debe ser positivo y no superar el comprobante original.']);
            }
            $credited = (float) BillingDocument::query()
                ->where('organization_id', $lockedOriginal->organization_id)
                ->where('related_document_id', $lockedOriginal->id)
                ->where('document_type', 'credit_note')
                ->where('status', '!=', 'voided')
                ->sum('total');
            if (round($credited + $total, 2) > round((float) $lockedOriginal->total, 2)) {
                throw ValidationException::withMessages(['total' => 'Las notas de credito acumuladas no pueden superar el comprobante original.']);
            }

            return BillingDocument::query()->create([
                'organization_id' => $original->organization_id,
                'order_id' => $original->order_id,
                'related_document_id' => $original->id,
                'idempotency_key' => $key,
                'payload_hash' => $hash,
                'branch_id' => $original->branch_id,
                'provider' => $original->provider,
                'document_type' => 'credit_note',
                'credit_note_reason_code' => $reasonCode,
                'credit_note_reason' => $reason,
                'series' => $series,
                'number' => $number,
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'customer_document_type' => $original->customer_document_type,
                'customer_document_number' => $original->customer_document_number,
                'subtotal' => round($total / 1.18, 2),
                'tax' => round($total - ($total / 1.18), 2),
                'total' => $total,
                'currency' => $original->currency,
                'status' => 'draft',
                'request_payload' => ['original_document_id' => $original->id, 'reason_code' => $reasonCode, 'reason' => $reason],
            ]);
        });
    }

    /** @param array<string, mixed> $evidence */
    public function registerExternalIssuance(BillingDocument $creditNote, array $evidence): BillingDocument
    {
        $reference = trim((string) ($evidence['provider_reference'] ?? ''));
        if ($creditNote->document_type !== 'credit_note' || $reference === '') {
            throw ValidationException::withMessages(['provider_reference' => 'La evidencia de emision externa es obligatoria para la nota de credito.']);
        }

        return DB::transaction(function () use ($creditNote, $evidence, $reference): BillingDocument {
            $locked = BillingDocument::query()
                ->where('organization_id', $creditNote->organization_id)
                ->lockForUpdate()
                ->findOrFail($creditNote->id);
            if ($locked->status === 'issued') {
                if ((string) data_get($locked->response_payload, 'provider_reference') !== $reference) {
                    throw ValidationException::withMessages(['provider_reference' => 'La nota de credito ya fue registrada con otra evidencia.']);
                }

                if ($locked->order) {
                    $this->economicEvents->recordCreditNote($locked->order, $locked);
                }

                return $locked;
            }
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['document' => 'Solo una nota de credito en borrador puede registrar emision externa.']);
            }

            $locked->forceFill([
                'status' => 'issued',
                'issued_at' => $evidence['issued_at'] ?? now(),
                'response_payload' => [...$evidence, 'provider_reference' => $reference],
                'sunat_ticket' => $evidence['sunat_ticket'] ?? null,
                'sunat_cdr_code' => $evidence['sunat_cdr_code'] ?? null,
                'sunat_cdr_description' => $evidence['sunat_cdr_description'] ?? null,
            ])->save();

            if ($locked->order) {
                $this->economicEvents->recordCreditNote($locked->order, $locked);
            }

            return $locked;
        });
    }
}

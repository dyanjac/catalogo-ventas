<?php

declare(strict_types=1);

namespace Modules\Transport\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Models\BillingDocument;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryTransfer;
use Modules\Security\Models\SecurityBranch;
use Modules\Transport\Enums\TransportEnvironment;
use Modules\Transport\Enums\TransportGuideStatus;
use Modules\Transport\Enums\TransportGuideType;
use Modules\Transport\Enums\TransportMode;

class TransportGuide extends Model
{
    use BelongsToOrganization;

    private const IMMUTABLE_FIELDS = [
        'organization_id', 'branch_id', 'idempotency_key', 'payload_hash', 'guide_type', 'series', 'number',
        'reason_code', 'reason_catalog_version', 'transport_mode', 'issue_date', 'transfer_at',
        'origin_snapshot', 'destination_snapshot', 'recipient_snapshot', 'transport_snapshot',
        'gross_weight', 'weight_unit', 'package_count', 'inventory_document_id', 'inventory_transfer_id',
        'billing_document_id', 'related_guide_id', 'external_sender_snapshot', 'exception_justification', 'created_by',
        'request_payload', 'notes', 'provider', 'environment', 'provider_config_hash',
    ];

    protected $fillable = [
        ...self::IMMUTABLE_FIELDS,
        'status', 'provider', 'environment', 'provider_ticket', 'provider_code', 'provider_description',
        'response_payload', 'xml_disk', 'xml_path', 'xml_hash', 'cdr_disk', 'cdr_path',
        'cdr_hash', 'queued_at', 'submitted_at', 'accepted_at', 'rejected_at', 'voided_at',
    ];

    protected $casts = [
        'guide_type' => TransportGuideType::class,
        'status' => TransportGuideStatus::class,
        'transport_mode' => TransportMode::class,
        'environment' => TransportEnvironment::class,
        'issue_date' => 'date',
        'transfer_at' => 'datetime',
        'origin_snapshot' => 'array',
        'destination_snapshot' => 'array',
        'recipient_snapshot' => 'array',
        'transport_snapshot' => 'array',
        'external_sender_snapshot' => 'array',
        'gross_weight' => 'decimal:3',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'queued_at' => 'datetime',
        'submitted_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $guide): void {
            if ($guide->isDirty(self::IMMUTABLE_FIELDS)) {
                throw new \LogicException('El contenido documental de una GRE es inmutable. Emita una nueva guia para corregirlo.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Las guias de remision no se eliminan.'));
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        return $query->forCurrentOrganization()->where($field ?? $this->getRouteKeyName(), $value);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SecurityBranch::class, 'branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inventoryDocument(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class, 'inventory_document_id');
    }

    public function inventoryTransfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'inventory_transfer_id');
    }

    public function billingDocument(): BelongsTo
    {
        return $this->belongsTo(BillingDocument::class, 'billing_document_id');
    }

    public function relatedGuide(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_guide_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransportGuideItem::class, 'transport_guide_id')->orderBy('line_number');
    }

    public function transmissions(): HasMany
    {
        return $this->hasMany(TransportGuideTransmission::class, 'transport_guide_id')->orderBy('id');
    }

    public function formattedNumber(): string
    {
        return $this->series.'-'.str_pad((string) $this->number, 8, '0', STR_PAD_LEFT);
    }
}

<?php

namespace Modules\Billing\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Orders\Entities\Order;
use Modules\Security\Models\SecurityBranch;

class BillingDocument extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'order_id',
        'related_document_id',
        'idempotency_key',
        'payload_hash',
        'organization_id',
        'branch_id',
        'provider',
        'document_type',
        'credit_note_reason_code',
        'credit_note_reason',
        'series',
        'number',
        'issue_date',
        'customer_document_type',
        'customer_document_number',
        'subtotal',
        'tax',
        'total',
        'currency',
        'status',
        'sunat_ticket',
        'sunat_cdr_code',
        'sunat_cdr_description',
        'request_payload',
        'response_payload',
        'xml_path',
        'xml_hash',
        'issued_at',
        'voided_at',
        'return_requested_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'issued_at' => 'datetime',
        'voided_at' => 'datetime',
        'return_requested_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SecurityBranch::class, 'branch_id');
    }

    public function relatedDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_document_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(self::class, 'related_document_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(BillingDocumentFile::class, 'billing_document_id');
    }

    public function responseHistories(): HasMany
    {
        return $this->hasMany(BillingDocumentResponseHistory::class, 'billing_document_id');
    }

    public function xmlFile(): ?BillingDocumentFile
    {
        return $this->files->firstWhere('file_type', 'xml')
            ?? $this->files()->where('file_type', 'xml')->latest('id')->first();
    }

    public function cdrFile(): ?BillingDocumentFile
    {
        return $this->files->firstWhere('file_type', 'cdr')
            ?? $this->files()->where('file_type', 'cdr')->latest('id')->first();
    }
}

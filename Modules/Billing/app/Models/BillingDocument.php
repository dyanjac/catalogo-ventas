<?php

namespace Modules\Billing\Models;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingDocument extends Model
{
    protected $fillable = [
        'order_id',
        'provider',
        'document_type',
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
        'issued_at',
        'voided_at',
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
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

<?php

namespace Modules\Billing\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingDocumentResponseHistory extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'billing_document_id',
        'organization_id',
        'provider',
        'environment',
        'event',
        'ok',
        'status_code',
        'message',
        'request_payload',
        'response_payload',
        'error_class',
        'error_message',
    ];

    protected $casts = [
        'ok' => 'boolean',
        'status_code' => 'integer',
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(BillingDocument::class, 'billing_document_id');
    }
}

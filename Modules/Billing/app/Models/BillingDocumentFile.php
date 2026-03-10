<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingDocumentFile extends Model
{
    protected $fillable = [
        'billing_document_id',
        'file_type',
        'storage_disk',
        'storage_path',
        'mime_type',
        'size',
        'hash_sha256',
        'metadata',
    ];

    protected $casts = [
        'size' => 'integer',
        'metadata' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(BillingDocument::class, 'billing_document_id');
    }
}

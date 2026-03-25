<?php

namespace Modules\Billing\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'enabled',
        'country',
        'provider',
        'environment',
        'dispatch_mode',
        'queue_connection',
        'queue_name',
        'provider_credentials',
        'invoice_series',
        'receipt_series',
        'credit_note_series',
        'debit_note_series',
        'default_invoice_operation_code',
        'default_receipt_operation_code',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'provider_credentials' => 'array',
    ];
}

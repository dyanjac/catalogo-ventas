<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    protected $fillable = [
        'enabled',
        'country',
        'provider',
        'environment',
        'provider_credentials',
        'invoice_series',
        'receipt_series',
        'credit_note_series',
        'debit_note_series',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'provider_credentials' => 'array',
    ];
}

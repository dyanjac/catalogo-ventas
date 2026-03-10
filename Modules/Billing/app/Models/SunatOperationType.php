<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class SunatOperationType extends Model
{
    protected $table = 'billing_sunat_operation_types';

    protected $fillable = [
        'code',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}


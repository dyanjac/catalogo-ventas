<?php

declare(strict_types=1);

namespace Modules\Orders\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Modules\Orders\Enums\SalesInventoryChannelMode;

class SalesInventoryChannelRollout extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'channel',
        'mode',
        'activated_at',
        'meta',
    ];

    protected $casts = [
        'mode' => SalesInventoryChannelMode::class,
        'activated_at' => 'datetime',
        'meta' => 'array',
    ];
}

<?php

declare(strict_types=1);

namespace Modules\Transport\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportGuideTransmission extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'transport_guide_id', 'idempotency_key', 'operation', 'status_before',
        'status_after', 'attempt_number', 'request_payload', 'response_payload', 'occurred_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Las transmisiones GRE son inmutables.'));
        static::deleting(fn () => throw new \LogicException('Las transmisiones GRE son inmutables.'));
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(TransportGuide::class, 'transport_guide_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Operations\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class OperationalReconciliationIssue extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'run_id', 'organization_id', 'domain', 'issue_code', 'severity', 'source_type',
        'source_id', 'source_code', 'fingerprint', 'expected', 'actual', 'context',
    ];

    protected $casts = [
        'organization_id' => 'integer', 'source_id' => 'integer', 'expected' => 'array',
        'actual' => 'array', 'context' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las incidencias de conciliación son inmutables.'));
        static::deleting(fn () => throw new LogicException('Las incidencias de conciliación son inmutables.'));
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(OperationalReconciliationRun::class, 'run_id');
    }
}

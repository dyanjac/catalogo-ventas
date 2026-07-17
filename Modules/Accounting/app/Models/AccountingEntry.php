<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class AccountingEntry extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'economic_event_id',
        'origin',
        'reversal_of_id',
        'payload_hash',
        'entry_date',
        'period_year',
        'period_month',
        'voucher_type',
        'voucher_series',
        'voucher_number',
        'reference',
        'description',
        'status',
        'total_debit',
        'total_credit',
        'posted_at',
        'created_by',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'economic_event_id' => 'integer',
        'reversal_of_id' => 'integer',
        'entry_date' => 'date',
        'posted_at' => 'datetime',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $entry): void {
            if (in_array($entry->getRawOriginal('status'), ['posted', 'voided'], true)) {
                throw new LogicException('Los asientos publicados o anulados son inmutables; use una reversión.');
            }
        });
        static::deleting(function (self $entry): void {
            if (in_array($entry->getRawOriginal('status'), ['posted', 'voided'], true)) {
                throw new LogicException('Los asientos publicados o anulados son inmutables; use una reversión.');
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingEntryLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function economicEvent(): BelongsTo
    {
        return $this->belongsTo(AccountingEconomicEvent::class, 'economic_event_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AccountingEntryAttachment::class);
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $field ??= $this->getRouteKeyName();

        return $query->forCurrentOrganization()->where($field, $value);
    }
}

<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingEntry extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
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
        'entry_date' => 'date',
        'posted_at' => 'datetime',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingEntryLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

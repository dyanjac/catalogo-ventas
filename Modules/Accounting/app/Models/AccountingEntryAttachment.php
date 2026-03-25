<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingEntryAttachment extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'accounting_entry_id',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'organization_id' => 'integer',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

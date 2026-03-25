<?php

namespace Modules\ElectronicDocuments\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    use BelongsToOrganization;

    public const TYPES = [
        'factura',
        'boleta',
        'nota_credito',
        'nota_debito',
        'retencion',
        'recibo_honorarios',
    ];

    protected $fillable = [
        'organization_id',
        'name',
        'document_type',
        'xslt_content',
        'is_active',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeByDocumentType(Builder $query, string $documentType): Builder
    {
        return $query->where('document_type', $documentType);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $field ??= $this->getRouteKeyName();

        return $query->forCurrentOrganization()->where($field, $value);
    }

    public static function activeForType(string $type, ?int $organizationId = null): ?self
    {
        return static::query()
            ->where('document_type', $type)
            ->where('is_active', true)
            ->when($organizationId !== null, function (Builder $query) use ($organizationId) {
                $query->where(function (Builder $subQuery) use ($organizationId) {
                    $subQuery->where('organization_id', $organizationId)
                        ->orWhereNull('organization_id');
                })->orderByRaw('CASE WHEN organization_id = ? THEN 0 ELSE 1 END', [$organizationId]);
            }, function (Builder $query) {
                $query->whereNull('organization_id');
            })
            ->orderByDesc('id')
            ->first();
    }
}

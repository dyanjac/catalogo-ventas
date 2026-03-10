<?php

namespace Modules\ElectronicDocuments\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    public const TYPES = [
        'factura',
        'boleta',
        'nota_credito',
        'nota_debito',
        'retencion',
        'recibo_honorarios',
    ];

    protected $fillable = [
        'company_id',
        'name',
        'document_type',
        'xslt_content',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
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

    public static function activeForType(string $type, ?int $companyId = null): ?self
    {
        return static::query()
            ->where('document_type', $type)
            ->where('is_active', true)
            ->when($companyId !== null, function (Builder $query) use ($companyId) {
                $query->where(function (Builder $subQuery) use ($companyId) {
                    $subQuery->where('company_id', $companyId)
                        ->orWhereNull('company_id');
                })->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId]);
            }, function (Builder $query) {
                $query->whereNull('company_id');
            })
            ->orderByDesc('id')
            ->first();
    }
}


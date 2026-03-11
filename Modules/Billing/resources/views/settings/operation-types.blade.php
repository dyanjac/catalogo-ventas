@extends('layouts.admin')

@section('title', 'Catálogo SUNAT 51')

@php
    $opTypes = $operationTypes ?? collect();
@endphp

@section('content')
<div class="billing-operation-types-page py-2">
    <x-admin.page-header title="Catálogo SUNAT 51 - Tipo de operación">
        <x-slot:actions>
            <a href="{{ route('admin.billing.settings.edit') }}" class="btn btn-light border rounded-pill px-4">Volver a configuración</a>
            <a href="{{ route('admin.billing.documents.index') }}" class="btn btn-light border rounded-pill px-4">Ver documentos</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.billing.operation-types.update') }}" class="card border-0 billing-op-card">
        @csrf
        @method('PUT')

        <div class="card-body p-3 p-md-4">
            <div class="billing-op-block mb-4">
                <div class="billing-op-block__header">
                    <h5 class="mb-1">Valores por defecto</h5>
                    <p class="text-muted mb-0">Define el tipo de operación base para factura y boleta electrónica.</p>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <label class="form-label">Default Factura</label>
                        <select name="default_invoice_operation_code" class="form-select">
                            @foreach($opTypes as $type)
                                <option value="{{ $type->code }}"
                                    @selected(old('default_invoice_operation_code', $setting->default_invoice_operation_code ?? '01') === $type->code)>
                                    {{ $type->code }} - {{ $type->description }}{{ $type->is_active ? '' : ' (inactivo)' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Default Boleta</label>
                        <select name="default_receipt_operation_code" class="form-select">
                            @foreach($opTypes as $type)
                                <option value="{{ $type->code }}"
                                    @selected(old('default_receipt_operation_code', $setting->default_receipt_operation_code ?? '01') === $type->code)>
                                    {{ $type->code }} - {{ $type->description }}{{ $type->is_active ? '' : ' (inactivo)' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="billing-op-block">
                <div class="billing-op-block__header">
                    <h5 class="mb-1">Códigos disponibles</h5>
                    <p class="text-muted mb-0">Administra descripción y vigencia de cada código del catálogo SUNAT 51.</p>
                </div>

                <div class="table-responsive billing-op-table mt-3">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead>
                        <tr>
                            <th style="width: 90px;">Código</th>
                            <th>Descripción</th>
                            <th class="text-center" style="width: 100px;">Activo</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($opTypes as $type)
                            <tr>
                                <td class="font-weight-bold">{{ $type->code }}</td>
                                <td>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           name="operation_types[{{ $type->code }}][description]"
                                           value="{{ old("operation_types.{$type->code}.description", $type->description) }}">
                                </td>
                                <td class="text-center">
                                    <input type="hidden" name="operation_types[{{ $type->code }}][enabled]" value="0">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           name="operation_types[{{ $type->code }}][enabled]"
                                           value="1"
                                           @checked(old("operation_types.{$type->code}.enabled", $type->is_active))>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card-footer billing-op-footer d-flex justify-content-between align-items-center flex-wrap">
            <small class="text-muted mb-0">Solo puedes asignar como valor por defecto códigos que estén activos.</small>
            <button class="btn btn-primary rounded-pill px-4">Guardar catálogo</button>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    .billing-operation-types-page .billing-op-card {
        border: 1px solid var(--admin-card-border) !important;
        border-radius: 1rem;
        box-shadow: 0 12px 24px rgba(31, 45, 61, .05);
        background: #fff;
    }

    .billing-operation-types-page .billing-op-block {
        border: 1px solid #e9ecef;
        border-radius: .75rem;
        padding: 1rem;
        background: #fff;
    }

    .billing-operation-types-page .billing-op-block__header h5 {
        font-weight: 700;
        color: #1f2d3d;
    }

    .billing-operation-types-page .billing-op-table thead th {
        background: #f8f9fb;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    .billing-operation-types-page .billing-op-footer {
        position: sticky;
        bottom: 0;
        background: #fff;
        border-top: 1px solid #e5e7eb;
        z-index: 10;
    }
</style>
@endpush

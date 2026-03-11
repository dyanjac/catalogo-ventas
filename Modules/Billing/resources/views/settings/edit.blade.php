@extends('layouts.admin')

@section('title', 'Facturación electrónica')

@php
    $creds = old('provider_credentials', $setting->provider_credentials ?? []);
    $greenter = $creds['greenter'] ?? [];
    $nubefact = $creds['nubefact'] ?? [];
    $tefacturo = $creds['tefacturo'] ?? [];
    $efact = $creds['efact'] ?? [];
    $opTypes = $operationTypes ?? collect();
@endphp

@section('content')
<div class="billing-settings-page py-2">
    <x-admin.page-header title="Facturación electrónica Perú">
        <x-slot:actions>
            <a href="{{ route('admin.billing.documents.index') }}" class="btn btn-light border rounded-pill px-4">Ver documentos</a>
            <a href="{{ route('admin.electronic-documents.templates.index') }}" class="btn btn-light border rounded-pill px-4">Plantillas PDF</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.billing.settings.update') }}" class="card billing-main-card border-0 mb-4">
        @csrf
        @method('PUT')

        <div class="card-body p-3 p-md-4">
            <div class="billing-block mb-4">
                <div class="billing-block__header">
                    <h5 class="mb-1">Configuración general</h5>
                    <p class="text-muted mb-0">Define proveedor, entorno y modo de declaración de comprobantes.</p>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-3">
                        <label class="form-label">País</label>
                        <input type="text" name="country" class="form-control" value="{{ old('country', $setting->country) }}" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Proveedor activo</label>
                        <select name="provider" class="form-select" required>
                            @foreach($providers as $code => $label)
                                <option value="{{ $code }}" @selected(old('provider', $setting->provider) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ambiente</label>
                        <select name="environment" class="form-select" required>
                            @foreach($environments as $env)
                                <option value="{{ $env }}" @selected(old('environment', $setting->environment) === $env)>{{ strtoupper($env) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check billing-toggle w-100">
                            <input type="hidden" name="enabled" value="0">
                            <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" @checked(old('enabled', $setting->enabled))>
                            <label class="form-check-label" for="enabled">Activar módulo</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Modo declaración</label>
                        <select name="dispatch_mode" id="dispatch_mode" class="form-select" required>
                            <option value="sync" @selected(old('dispatch_mode', $setting->dispatch_mode ?? 'sync') === 'sync')>Sin cola (en línea)</option>
                            <option value="queue" @selected(old('dispatch_mode', $setting->dispatch_mode ?? 'sync') === 'queue')>Con cola</option>
                        </select>
                    </div>
                    <div class="col-md-4 queue-field">
                        <label class="form-label">Conexión de cola</label>
                        <input type="text" name="queue_connection" id="queue_connection" class="form-control" placeholder="rabbitmq / redis / database" value="{{ old('queue_connection', $setting->queue_connection) }}">
                    </div>
                    <div class="col-md-4 queue-field">
                        <label class="form-label">Nombre de cola</label>
                        <input type="text" name="queue_name" id="queue_name" class="form-control" placeholder="billing" value="{{ old('queue_name', $setting->queue_name) }}">
                    </div>
                </div>
            </div>

            <div class="billing-block mb-4">
                <div class="billing-block__header">
                    <h5 class="mb-1">Catálogo SUNAT 51 - Tipo de operación</h5>
                    <p class="text-muted mb-0">Configura los tipos de operación habilitados y los valores por defecto.</p>
                </div>

                <div class="row g-3 mb-3 mt-1">
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

                <div class="table-responsive billing-op-table">
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

            <div class="billing-block mb-4">
                <div class="billing-block__header">
                    <h5 class="mb-1">Series de comprobantes</h5>
                    <p class="text-muted mb-0">Series por tipo de documento para emisión electrónica.</p>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-3">
                        <label class="form-label">Factura</label>
                        <input type="text" name="invoice_series" class="form-control text-uppercase" maxlength="10" value="{{ old('invoice_series', $setting->invoice_series) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Boleta</label>
                        <input type="text" name="receipt_series" class="form-control text-uppercase" maxlength="10" value="{{ old('receipt_series', $setting->receipt_series) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nota crédito</label>
                        <input type="text" name="credit_note_series" class="form-control text-uppercase" maxlength="10" value="{{ old('credit_note_series', $setting->credit_note_series) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nota débito</label>
                        <input type="text" name="debit_note_series" class="form-control text-uppercase" maxlength="10" value="{{ old('debit_note_series', $setting->debit_note_series) }}">
                    </div>
                </div>
            </div>

            <div class="billing-block mb-4">
                <div class="billing-block__header">
                    <h5 class="mb-1">Credenciales de proveedores</h5>
                    <p class="text-muted mb-0">Completa los datos del proveedor activo y deja preconfigurados los alternos.</p>
                </div>

                <div class="provider-card mt-3">
                    <h6 class="provider-title">Greenter (SUNAT/OSE)</h6>
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">RUC</label>
                            <input type="text" name="provider_credentials[greenter][ruc]" class="form-control" value="{{ $greenter['ruc'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">SOL Usuario</label>
                            <input type="text" name="provider_credentials[greenter][sol_user]" class="form-control" value="{{ $greenter['sol_user'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">SOL Clave</label>
                            <input type="password" name="provider_credentials[greenter][sol_password]" class="form-control" value="{{ $greenter['sol_password'] ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ruta certificado</label>
                            <input type="text" name="provider_credentials[greenter][certificate_path]" class="form-control" placeholder="storage/app/certificados/empresa.pem" value="{{ $greenter['certificate_path'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Clave certificado</label>
                            <input type="password" name="provider_credentials[greenter][certificate_password]" class="form-control" value="{{ $greenter['certificate_password'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">UBL Version</label>
                            <select name="provider_credentials[greenter][ubl_version]" class="form-select">
                                <option value="2.0" @selected(($greenter['ubl_version'] ?? '2.0') === '2.0')>2.0</option>
                                <option value="2.1" @selected(($greenter['ubl_version'] ?? '') === '2.1')>2.1</option>
                            </select>
                        </div>
                    </div>

                    <h6 class="provider-subtitle mt-4">Datos del emisor (Greenter)</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Razón social</label>
                            <input type="text" name="provider_credentials[greenter][company_business_name]" class="form-control" placeholder="EMPRESA S.A.C." value="{{ $greenter['company_business_name'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre comercial</label>
                            <input type="text" name="provider_credentials[greenter][company_trade_name]" class="form-control" placeholder="MI NEGOCIO" value="{{ $greenter['company_trade_name'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Ubigeo</label>
                            <input type="text" name="provider_credentials[greenter][company_ubigeo]" class="form-control" placeholder="150101" value="{{ $greenter['company_ubigeo'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Departamento</label>
                            <input type="text" name="provider_credentials[greenter][company_department]" class="form-control" placeholder="LIMA" value="{{ $greenter['company_department'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Provincia</label>
                            <input type="text" name="provider_credentials[greenter][company_province]" class="form-control" placeholder="LIMA" value="{{ $greenter['company_province'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Distrito</label>
                            <input type="text" name="provider_credentials[greenter][company_district]" class="form-control" placeholder="LIMA" value="{{ $greenter['company_district'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Urbanización</label>
                            <input type="text" name="provider_credentials[greenter][company_urbanization]" class="form-control" placeholder="-" value="{{ $greenter['company_urbanization'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Código local</label>
                            <input type="text" name="provider_credentials[greenter][company_local_code]" class="form-control" placeholder="0000" value="{{ $greenter['company_local_code'] ?? '' }}">
                        </div>
                        <div class="col-md-10">
                            <label class="form-label">Dirección fiscal</label>
                            <input type="text" name="provider_credentials[greenter][company_address]" class="form-control" placeholder="Dirección completa del emisor" value="{{ $greenter['company_address'] ?? '' }}">
                        </div>
                    </div>
                </div>

                <div class="provider-card mt-3">
                    <h6 class="provider-title">NubeFact</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">API URL</label>
                            <input type="text" name="provider_credentials[nubefact][api_url]" class="form-control" value="{{ $nubefact['api_url'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Token</label>
                            <input type="password" name="provider_credentials[nubefact][api_token]" class="form-control" value="{{ $nubefact['api_token'] ?? '' }}">
                        </div>
                    </div>
                </div>

                <div class="provider-card mt-3">
                    <h6 class="provider-title">TeFacturo</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">API URL</label>
                            <input type="text" name="provider_credentials[tefacturo][api_url]" class="form-control" value="{{ $tefacturo['api_url'] ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Usuario</label>
                            <input type="text" name="provider_credentials[tefacturo][api_user]" class="form-control" value="{{ $tefacturo['api_user'] ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Clave</label>
                            <input type="password" name="provider_credentials[tefacturo][api_password]" class="form-control" value="{{ $tefacturo['api_password'] ?? '' }}">
                        </div>
                    </div>
                </div>

                <div class="provider-card mt-3">
                    <h6 class="provider-title">eFact</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">API URL</label>
                            <input type="text" name="provider_credentials[efact][api_url]" class="form-control" value="{{ $efact['api_url'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Token</label>
                            <input type="password" name="provider_credentials[efact][api_token]" class="form-control" value="{{ $efact['api_token'] ?? '' }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer billing-footer-sticky d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <small class="text-muted mb-0">Tip: prueba conexión después de guardar cambios de credenciales.</small>
            <div class="d-flex gap-2">
                <button class="btn btn-primary rounded-pill px-4">Guardar configuración</button>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.billing.settings.test-connection') }}" class="mb-3">
        @csrf
        <button class="btn btn-light border rounded-pill px-4">Probar conexión del proveedor activo</button>
    </form>

    <div class="alert alert-info mb-0">
        <strong>Nota:</strong> para usar Greenter en emisión real, instala la dependencia con <code>composer require greenter/greenter</code>.
    </div>
</div>
@endsection

@push('styles')
<style>
    .billing-settings-page .billing-main-card {
        border: 1px solid var(--admin-card-border) !important;
        border-radius: 1rem;
        box-shadow: 0 12px 24px rgba(31, 45, 61, .05);
        background: #fff;
    }

    .billing-settings-page .billing-block {
        border: 1px solid #e9ecef;
        border-radius: .75rem;
        padding: 1rem;
        background: #fff;
    }

    .billing-settings-page .billing-block__header h5 {
        font-weight: 700;
        color: #1f2d3d;
    }

    .billing-settings-page .billing-op-table thead th {
        background: #f8f9fb;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    .billing-settings-page .provider-card {
        border: 1px solid #e9ecef;
        border-radius: .65rem;
        padding: .9rem;
        background: #fcfcfd;
    }

    .billing-settings-page .provider-title {
        font-weight: 700;
        margin-bottom: .8rem;
        color: #223247;
    }

    .billing-settings-page .provider-subtitle {
        font-weight: 600;
        color: #40566f;
        margin-bottom: .8rem;
    }

    .billing-settings-page .billing-toggle {
        border: 1px solid #e4e8ee;
        border-radius: .5rem;
        padding: .625rem .75rem;
        min-height: calc(2.25rem + 2px);
        display: flex;
        align-items: center;
    }

    .billing-settings-page .billing-footer-sticky {
        position: sticky;
        bottom: 0;
        background: #fff;
        border-top: 1px solid #e5e7eb;
        z-index: 10;
    }

    @media (max-width: 767.98px) {
        .billing-settings-page .billing-footer-sticky {
            padding-bottom: calc(.75rem + env(safe-area-inset-bottom));
        }
    }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        var modeSelect = document.getElementById('dispatch_mode');
        if (!modeSelect) {
            return;
        }

        var queueFields = document.querySelectorAll('.queue-field input');

        function applyQueueState() {
            var isQueue = modeSelect.value === 'queue';
            queueFields.forEach(function (input) {
                input.disabled = !isQueue;
                input.closest('.queue-field').classList.toggle('opacity-50', !isQueue);
            });
        }

        modeSelect.addEventListener('change', applyQueueState);
        applyQueueState();
    })();
</script>
@endpush

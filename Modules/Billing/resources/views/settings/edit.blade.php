@extends('layouts.admin')

@section('title', 'Facturación electrónica')

@php
    $creds = old('provider_credentials', $setting->provider_credentials ?? []);
    $greenter = $creds['greenter'] ?? [];
    $nubefact = $creds['nubefact'] ?? [];
    $tefacturo = $creds['tefacturo'] ?? [];
    $efact = $creds['efact'] ?? [];
@endphp

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Facturación electrónica Perú">
            <x-slot:actions>
                <a href="{{ route('admin.billing.documents.index') }}" class="btn btn-light border rounded-pill px-4">Ver documentos</a>
            </x-slot:actions>
        </x-admin.page-header>

        <form method="POST" action="{{ route('admin.billing.settings.update') }}" class="card border border-secondary rounded-3 mb-4">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="row g-3">
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
                    <div class="col-md-3 d-flex align-items-center">
                        <div class="form-check mt-4">
                            <input type="hidden" name="enabled" value="0">
                            <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" @checked(old('enabled', $setting->enabled))>
                            <label class="form-check-label" for="enabled">Activar módulo</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Modo declaración</label>
                        <select name="dispatch_mode" class="form-select" required>
                            <option value="sync" @selected(old('dispatch_mode', $setting->dispatch_mode ?? 'sync') === 'sync')>Sin cola (en línea)</option>
                            <option value="queue" @selected(old('dispatch_mode', $setting->dispatch_mode ?? 'sync') === 'queue')>Con cola</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Conexión de cola</label>
                        <input type="text" name="queue_connection" class="form-control" placeholder="rabbitmq / redis / database" value="{{ old('queue_connection', $setting->queue_connection) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nombre de cola</label>
                        <input type="text" name="queue_name" class="form-control" placeholder="billing" value="{{ old('queue_name', $setting->queue_name) }}">
                    </div>
                </div>

                <hr>

                <h6 class="mb-3">Series</h6>
                <div class="row g-3">
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

                <hr>

                <h6 class="mb-3">Credenciales Greenter (SUNAT/OSE)</h6>
                <div class="row g-3 mb-4">
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

                <h6 class="mb-3">Datos del emisor (Greenter)</h6>
                <div class="row g-3 mb-4">
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

                <h6 class="mb-3">Credenciales NubeFact</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">API URL</label>
                        <input type="text" name="provider_credentials[nubefact][api_url]" class="form-control" value="{{ $nubefact['api_url'] ?? '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Token</label>
                        <input type="password" name="provider_credentials[nubefact][api_token]" class="form-control" value="{{ $nubefact['api_token'] ?? '' }}">
                    </div>
                </div>

                <h6 class="mb-3">Credenciales TeFacturo</h6>
                <div class="row g-3 mb-4">
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

                <h6 class="mb-3">Credenciales eFact</h6>
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

            <div class="card-footer">
                <button class="btn btn-primary rounded-pill px-4">Guardar configuración</button>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.billing.settings.test-connection') }}" class="mb-3">
            @csrf
            <button class="btn btn-light border rounded-pill px-4">Probar conexión del proveedor activo</button>
        </form>

        <div class="alert alert-info">
            <strong>Nota:</strong> para usar Greenter en emisión real, instala la dependencia con <code>composer require greenter/greenter</code>.
        </div>
    </div>
</div>
@endsection

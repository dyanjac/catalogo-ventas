@extends('layouts.admin')

@section('title', 'Configuracion GRE')

@section('content')
<div class="py-2"><x-admin.page-header title="Configuracion de transporte y GRE"><x-slot:actions><a href="{{ route('admin.transport.guides.index') }}" class="btn btn-outline-secondary">Ver guias</a></x-slot:actions></x-admin.page-header>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('admin.transport.settings.update') }}" class="card border-0 shadow-sm"><div class="card-body">@csrf @method('PUT')
    <div class="alert alert-info">La simulacion es el modo predeterminado. Produccion permanece bloqueada hasta validar las credenciales GRE actuales.</div>
    <div class="row g-3">
        <div class="col-md-2"><div class="form-check mt-4"><input type="checkbox" name="enabled" value="1" class="form-check-input" @checked($setting->enabled)><label class="form-check-label">Habilitado</label></div></div>
        <div class="col-md-2"><label class="form-label">Entorno</label><select name="environment" class="form-select"><option value="simulation" @selected($setting->environment->value === 'simulation')>Simulacion</option><option value="production" @selected($setting->environment->value === 'production')>Produccion</option></select></div>
        <div class="col-md-2"><label class="form-label">Proveedor</label><select name="provider" class="form-select"><option value="simulation" @selected($setting->provider === 'simulation')>Simulado</option><option value="greenter" @selected($setting->provider === 'greenter')>Greenter</option></select></div>
        <div class="col-md-2"><label class="form-label">Despacho</label><select name="dispatch_mode" class="form-select"><option value="queue" @selected($setting->dispatch_mode === 'queue')>Cola</option><option value="sync" @selected($setting->dispatch_mode === 'sync')>Sincrono</option></select></div>
        <div class="col-md-2"><label class="form-label">Serie remitente</label><input name="sender_series" class="form-control" value="{{ $setting->sender_series }}"></div>
        <div class="col-md-2"><label class="form-label">Serie transportista</label><input name="carrier_series" class="form-control" value="{{ $setting->carrier_series }}"></div>
        <input type="hidden" name="queue_name" value="{{ $setting->queue_name ?: 'transport' }}"><input type="hidden" name="queue_connection" value="{{ $setting->queue_connection }}">
        <div class="col-12"><div class="form-check"><input type="checkbox" name="allow_carrier_without_sender" value="1" class="form-check-input" @checked($setting->allow_carrier_without_sender)><label class="form-check-label">Permitir excepcion documentada para GRE transportista sin GRE remitente</label></div></div>
    </div><hr><h5>Credenciales Greenter GRE</h5><p class="text-muted">Los valores se almacenan cifrados. Los campos vacios conservan el valor actual.</p><div class="row g-3">
        @foreach(['company_ruc'=>'RUC emisor','company_legal_name'=>'Razon social','company_ubigeo'=>'Ubigeo emisor','company_address'=>'Direccion emisor','company_department'=>'Departamento','company_province'=>'Provincia','company_district'=>'Distrito','sol_user'=>'Usuario SOL','sol_password'=>'Clave SOL','api_client_id'=>'Client ID GRE','api_client_secret'=>'Client secret GRE','certificate_path'=>'Archivo certificado PEM'] as $key => $label)
            <div class="col-md-4"><label class="form-label">{{ $label }}</label><input type="{{ str_contains($key, 'password') || str_contains($key, 'secret') ? 'password' : 'text' }}" name="provider_credentials[{{ $key }}]" class="form-control" value="{{ in_array($key, ['sol_password','api_client_secret']) ? '' : data_get($setting->provider_credentials, $key) }}"></div>
        @endforeach
    </div><p class="form-text">Instala el certificado en <code>storage/app/private/transport/certificates</code> e ingresa solo el nombre del archivo, por ejemplo <code>empresa.pem</code>. Los endpoints SUNAT son fijos y no se reciben desde el formulario.</p>
</div><div class="card-footer d-flex justify-content-between"><span class="text-muted">Validado: {{ $setting->credentials_validated_at?->format('d/m/Y H:i:s') ?: 'No' }}</span><button class="btn btn-primary">Guardar</button></div></form>
<form method="POST" action="{{ route('admin.transport.settings.validate') }}" class="mt-3">@csrf<button class="btn btn-outline-success">Validar configuracion actual</button></form></div>
@endsection

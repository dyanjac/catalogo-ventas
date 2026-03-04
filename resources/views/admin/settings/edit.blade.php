@extends('layouts.admin')

@section('title', 'Configuracion del Comercio')
@section('page_title', 'Configuracion del Comercio')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Configuracion del Comercio" />
        <div class="row">
            <div class="col-lg-8">
                <x-admin.form-card
                    :action="route('admin.settings.update')"
                    method="PUT"
                    enctype="multipart/form-data"
                    submit-label="Guardar configuracion"
                    title="Datos principales del comercio"
                >
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Nombre de la empresa o compania</label>
                                <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $setting->company_name) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">RUC o identificador</label>
                                <input type="text" name="tax_id" class="form-control" value="{{ old('tax_id', $setting->tax_id) }}">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Correo</label>
                                <input type="email" name="email" class="form-control" value="{{ old('email', $setting->email) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Telefono</label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone', $setting->phone) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Celular</label>
                                <input type="text" name="mobile" class="form-control" value="{{ old('mobile', $setting->mobile) }}">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Direccion</label>
                                <input type="text" name="address" class="form-control" value="{{ old('address', $setting->address) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Logo de la empresa</label>
                                <input type="file" name="logo_file" class="form-control" accept="image/*">
                                <small class="text-muted">Formato recomendado: PNG o JPG, hasta 4 MB.</small>
                            </div>
                        </div>
                </x-admin.form-card>
            </div>

            <div class="col-lg-4">
                <x-admin.info-card title="Vista previa">
                        <div class="text-center mb-3">
                            @if($setting->logo_url)
                                <img src="{{ $setting->logo_url }}" alt="{{ $setting->company_name }}" class="img-fluid rounded border p-2 bg-white" style="max-height: 180px; object-fit: contain;">
                            @else
                                <div class="border rounded p-4 text-muted">Sin logo cargado</div>
                            @endif
                        </div>
                        <h4 class="mb-1">{{ $setting->company_name }}</h4>
                        <div class="text-muted mb-2">{{ $setting->email ?: 'Sin correo' }}</div>
                        <x-admin.detail-grid
                            :items="[
                                ['label' => 'RUC/ID', 'value' => $setting->tax_id ?: '-', 'class' => 'col-12'],
                                ['label' => 'Telefono', 'value' => $setting->phone ?: '-', 'class' => 'col-12'],
                                ['label' => 'Celular', 'value' => $setting->mobile ?: '-', 'class' => 'col-12'],
                                ['label' => 'Direccion', 'value' => $setting->address ?: '-', 'class' => 'col-12'],
                            ]"
                            columns="col-12"
                            class="mt-3"
                        />

                        @if($setting->logo_path)
                            <hr>
                            <form method="POST" action="{{ route('admin.settings.update') }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="company_name" value="{{ $setting->company_name }}">
                                <input type="hidden" name="tax_id" value="{{ $setting->tax_id }}">
                                <input type="hidden" name="address" value="{{ $setting->address }}">
                                <input type="hidden" name="phone" value="{{ $setting->phone }}">
                                <input type="hidden" name="mobile" value="{{ $setting->mobile }}">
                                <input type="hidden" name="email" value="{{ $setting->email }}">
                                <input type="hidden" name="remove_logo" value="1">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar logo</button>
                            </form>
                        @endif
                </x-admin.info-card>
            </div>
        </div>
    </div>
</div>
@endsection

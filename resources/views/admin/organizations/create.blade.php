@extends('layouts.admin')

@section('title', 'Nueva organización demo')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Nueva Organización Demo" description="Alta rápida de un tenant nuevo. La organización nace en DEMO y queda lista para operar con configuración mínima.">
            <a href="{{ route('admin.organizations.index') }}" class="btn btn-light border rounded-pill px-4">
                Volver al listado
            </a>
        </x-admin.page-header>

        <x-admin.form-card :action="route('admin.organizations.store')" submit-label="Crear organización demo" :cancel-href="route('admin.organizations.index')">
            <div class="alert alert-warning border-0">
                <strong>Provisionamiento rápido:</strong> esta acción crea la organización, la sucursal principal, el usuario administrador inicial, el branding base y los settings operativos en entorno <strong>DEMO</strong>.
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <h5 class="mb-3">Organización</h5>

                    <div class="mb-3">
                        <label class="form-label">Nombre de organización</label>
                        <input type="text" name="organization_name" value="{{ old('organization_name') }}" class="form-control" required>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Código interno</label>
                            <input type="text" name="organization_code" value="{{ old('organization_code') }}" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Slug</label>
                            <input type="text" name="organization_slug" value="{{ old('organization_slug') }}" class="form-control" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-0">
                        <div class="col-md-6">
                            <label class="form-label">Nombre de marca</label>
                            <input type="text" name="brand_name" value="{{ old('brand_name') }}" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tagline institucional</label>
                            <input type="text" name="tagline" value="{{ old('tagline') }}" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mt-0">
                        <div class="col-md-6">
                            <label class="form-label">RUC / Tax ID</label>
                            <input type="text" name="tax_id" value="{{ old('tax_id') }}" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email comercial</label>
                            <input type="email" name="contact_email" value="{{ old('contact_email') }}" class="form-control" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-0">
                        <div class="col-md-6">
                            <label class="form-label">Email de soporte</label>
                            <input type="email" name="support_email" value="{{ old('support_email') }}" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="phone" value="{{ old('phone') }}" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mt-0">
                        <div class="col-md-6">
                            <label class="form-label">Teléfono de soporte</label>
                            <input type="text" name="support_phone" value="{{ old('support_phone') }}" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ciudad</label>
                            <input type="text" name="city" value="{{ old('city') }}" class="form-control">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Dirección</label>
                        <textarea name="address" class="form-control" rows="3">{{ old('address') }}</textarea>
                    </div>
                </div>

                <div class="col-lg-6">
                    <h5 class="mb-3">Operación inicial</h5>

                    <div class="mb-3">
                        <label class="form-label">Sucursal principal</label>
                        <input type="text" name="branch_name" value="{{ old('branch_name', 'Sucursal Principal') }}" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Administrador inicial</label>
                        <input type="text" name="admin_name" value="{{ old('admin_name') }}" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Correo del administrador</label>
                        <input type="email" name="admin_email" value="{{ old('admin_email') }}" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Teléfono del administrador</label>
                        <input type="text" name="admin_phone" value="{{ old('admin_phone') }}" class="form-control">
                    </div>

                    <div class="alert alert-light border mt-4 mb-0">
                        <div class="fw-semibold mb-2">Resultado de esta fase</div>
                        <ul class="mb-0 ps-3">
                            <li>Entorno inicial fijo en <strong>DEMO</strong>.</li>
                            <li>Contraseña del admin generada automáticamente.</li>
                            <li>Marca, tagline y soporte quedan sembrados desde el inicio.</li>
                            <li>La activación a <strong>PRODUCCIÓN</strong> será una fase separada.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </x-admin.form-card>
    </div>
</div>
@endsection

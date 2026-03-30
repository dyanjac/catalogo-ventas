@extends('layouts.admin')

@section('title', $organization->name)

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header :title="$organization->name" description="Detalle de la organización provisionada.">
            <a href="{{ route('admin.organizations.index') }}" class="btn btn-light border rounded-pill px-4">
                Volver al listado
            </a>
        </x-admin.page-header>

        @if(session('success'))
            <div class="alert alert-success border-0">
                <div class="fw-semibold">{{ session('success') }}</div>
            </div>
        @endif

        @if(session('provisioned_credentials'))
            @php($credentials = session('provisioned_credentials'))
            <div class="alert alert-success border-0">
                <div class="fw-semibold mb-2">Provisionamiento completado</div>
                <div>Admin inicial: <strong>{{ $credentials['admin_email'] }}</strong></div>
                <div>Contraseña generada: <strong>{{ $credentials['generated_password'] }}</strong></div>
            </div>
        @endif

        @if(session('recovered_admin_credentials'))
            @php($recovered = session('recovered_admin_credentials'))
            <div class="alert alert-warning border-0">
                <div class="fw-semibold mb-2">Administrador inicial reconstruido</div>
                <div>Admin recuperado: <strong>{{ $recovered['admin_email'] }}</strong></div>
                <div>Contraseña generada: <strong>{{ $recovered['generated_password'] }}</strong></div>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger border-0">
                <div class="fw-semibold mb-2">{{ session('error') }}</div>
                @if(session('production_failed_checks'))
                    <ul class="mb-0 ps-3">
                        @foreach(session('production_failed_checks') as $failedCheck)
                            <li>{{ $failedCheck['label'] }}: {{ $failedCheck['message'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger border-0">
                <div class="fw-semibold mb-2">Se encontraron errores de validación.</div>
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php($allChecksOk = collect($productionChecks)->every(fn ($check) => $check['ok']))
        @php($branch = $organization->branches->sortByDesc('is_default')->first())
        @php($admin = $organization->users->sortBy('id')->first())
        @php($isProduction = $organization->environment === 'production')
        @php($missingPrimaryBranch = ! $organization->branches->contains(fn ($item) => $item->is_default))
        @php($missingInitialAdmin = ! $organization->users->contains(fn ($item) => $item->isSuperAdmin()))

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="mb-3">Organización</h5>
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Código</dt>
                            <dd class="col-sm-7">{{ $organization->code }}</dd>
                            <dt class="col-sm-5">Slug</dt>
                            <dd class="col-sm-7">{{ $organization->slug }}</dd>
                            <dt class="col-sm-5">Tax ID</dt>
                            <dd class="col-sm-7">{{ $organization->tax_id ?: '-' }}</dd>
                            <dt class="col-sm-5">Entorno</dt>
                            <dd class="col-sm-7">
                                <span class="badge {{ $isProduction ? 'bg-success' : 'bg-warning text-dark' }}">
                                    {{ strtoupper($organization->environment) }}
                                </span>
                            </dd>
                            <dt class="col-sm-5">Estado</dt>
                            <dd class="col-sm-7">{{ strtoupper($organization->status) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="mb-3">Sucursal principal</h5>
                        @if($branch)
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Nombre</dt>
                                <dd class="col-sm-7">{{ $branch->name }}</dd>
                                <dt class="col-sm-5">Código</dt>
                                <dd class="col-sm-7">{{ $branch->code }}</dd>
                                <dt class="col-sm-5">Activa</dt>
                                <dd class="col-sm-7">{{ $branch->is_active ? 'Sí' : 'No' }}</dd>
                            </dl>
                        @else
                            <p class="text-muted mb-0">No se encontró sucursal principal.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="mb-3">Administrador inicial</h5>
                        @if($admin)
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Nombre</dt>
                                <dd class="col-sm-7">{{ $admin->name }}</dd>
                                <dt class="col-sm-5">Email</dt>
                                <dd class="col-sm-7">{{ $admin->email }}</dd>
                                <dt class="col-sm-5">Rol</dt>
                                <dd class="col-sm-7">{{ strtoupper($admin->role ?? '-') }}</dd>
                                <dt class="col-sm-5">Activo</dt>
                                <dd class="col-sm-7">{{ $admin->is_active ? 'Sí' : 'No' }}</dd>
                            </dl>
                        @else
                            <p class="text-muted mb-0">No se encontró usuario administrador.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($missingPrimaryBranch || $missingInitialAdmin)
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h5 class="mb-1">Recuperación guiada</h5>
                            <p class="text-muted mb-0">El tenant quedó incompleto. Puedes reconstruir sus piezas mínimas sin tocar el resto de la configuración.</p>
                        </div>
                        <span class="badge bg-danger">ATENCIÓN</span>
                    </div>

                    <div class="row g-3">
                        @if($missingPrimaryBranch)
                            <div class="col-lg-6">
                                <div class="border rounded-4 p-3 h-100 bg-light">
                                    <div class="fw-semibold mb-2">Falta la sucursal principal</div>
                                    <p class="text-muted small mb-3">Se recreará una sucursal default con nombre <strong>Sucursal Principal</strong> y estado activo.</p>
                                    <form action="{{ route('admin.organizations.primary-branch.recover', $organization) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-primary rounded-pill px-4">
                                            Reconstruir sucursal principal
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endif

                        @if($missingInitialAdmin)
                            <div class="col-lg-6">
                                <div class="border rounded-4 p-3 h-100 bg-light">
                                    <div class="fw-semibold mb-2">Falta el administrador inicial</div>
                                    <p class="text-muted small mb-3">Se recreará un usuario <strong>super_admin</strong> interno, enlazado a la sucursal principal, con contraseña nueva generada automáticamente.</p>
                                    <form action="{{ route('admin.organizations.initial-admin.recover', $organization) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-dark rounded-pill px-4" {{ $missingPrimaryBranch ? 'disabled' : '' }}>
                                            Reconstruir administrador inicial
                                        </button>
                                    </form>
                                    @if($missingPrimaryBranch)
                                        <div class="small text-muted mt-2">Primero debes reconstruir la sucursal principal.</div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="alert {{ $isProduction ? 'alert-warning' : 'alert-light' }} border mt-4 mb-0">
            <div class="fw-semibold mb-1">Guardas operativas</div>
            <div class="small mb-0">
                @if($isProduction)
                    En <strong>PRODUCTION</strong> no se permite dejar sin <strong>Tax ID</strong> a la organización, desactivar la <strong>sucursal principal</strong> ni desactivar el <strong>administrador inicial</strong>.
                @else
                    Mientras el tenant siga en <strong>DEMO</strong>, puedes ajustar estos datos con más libertad. Las restricciones duras empiezan cuando la organización pasa a <strong>PRODUCTION</strong>.
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h5 class="mb-1">Datos base de organización</h5>
                        <p class="text-muted mb-0">Esta primera parte de la Fase 3 permite mantener identidad organizacional y datos comerciales básicos del tenant.</p>
                    </div>
                    <span class="badge bg-light text-dark border">FASE 3A</span>
                </div>

                <form action="{{ route('admin.organizations.update', $organization) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h6 class="mb-3">Identidad organizacional</h6>

                            <div class="mb-3">
                                <label class="form-label">Nombre de organización</label>
                                <input type="text" name="organization_name" value="{{ old('organization_name', $organization->name) }}" class="form-control" required>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Código interno</label>
                                    <input type="text" name="organization_code" value="{{ old('organization_code', $organization->code) }}" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Slug</label>
                                    <input type="text" name="organization_slug" value="{{ old('organization_slug', $organization->slug) }}" class="form-control" required>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label">RUC / Tax ID</label>
                                <input type="text" name="tax_id" value="{{ old('tax_id', $organization->tax_id) }}" class="form-control" {{ $isProduction ? 'required' : '' }}>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <h6 class="mb-3">Datos comerciales base</h6>

                            <div class="mb-3">
                                <label class="form-label">Nombre comercial</label>
                                <input type="text" name="company_name" value="{{ old('company_name', $commerceSetting?->company_name ?: $organization->name) }}" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email comercial</label>
                                <input type="email" name="contact_email" value="{{ old('contact_email', $commerceSetting?->email) }}" class="form-control" required>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono</label>
                                    <input type="text" name="phone" value="{{ old('phone', $commerceSetting?->phone) }}" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Celular</label>
                                    <input type="text" name="mobile" value="{{ old('mobile', $commerceSetting?->mobile) }}" class="form-control">
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label">Dirección comercial</label>
                                <textarea name="address" class="form-control" rows="4">{{ old('address', $commerceSetting?->address) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            Guardar cambios base
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h5 class="mb-1">Sucursal principal</h5>
                        <p class="text-muted mb-0">Esta parte de la Fase 3 permite mantener la sucursal principal operativa del tenant.</p>
                    </div>
                    <span class="badge bg-light text-dark border">FASE 3B</span>
                </div>

                <form action="{{ route('admin.organizations.primary-branch.update', $organization) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="branch_is_active" value="0">

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label class="form-label">Nombre de sucursal</label>
                                <input type="text" name="branch_name" value="{{ old('branch_name', $branch?->name ?: 'Sucursal Principal') }}" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Código de sucursal</label>
                                <input type="text" name="branch_code" value="{{ old('branch_code', $branch?->code ?: 'MAIN') }}" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Ciudad</label>
                                <input type="text" name="branch_city" value="{{ old('branch_city', $branch?->city) }}" class="form-control">
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="branch_phone" value="{{ old('branch_phone', $branch?->phone) }}" class="form-control">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Dirección</label>
                                <textarea name="branch_address" class="form-control" rows="4">{{ old('branch_address', $branch?->address) }}</textarea>
                            </div>

                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" value="1" id="branch_is_active" name="branch_is_active" {{ old('branch_is_active', $branch?->is_active ? '1' : '0') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="branch_is_active">
                                    Sucursal principal activa
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-outline-primary rounded-pill px-4">
                            Guardar sucursal principal
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h5 class="mb-1">Administrador inicial</h5>
                        <p class="text-muted mb-0">Esta parte de la Fase 3 permite mantener el usuario admin inicial del tenant, incluyendo estado y cambio opcional de contraseña.</p>
                    </div>
                    <span class="badge bg-light text-dark border">FASE 3C</span>
                </div>

                <form action="{{ route('admin.organizations.initial-admin.update', $organization) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="admin_is_active" value="0">

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label class="form-label">Nombre del administrador</label>
                                <input type="text" name="admin_name" value="{{ old('admin_name', $admin?->name) }}" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Correo del administrador</label>
                                <input type="email" name="admin_email" value="{{ old('admin_email', $admin?->email) }}" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Teléfono del administrador</label>
                                <input type="text" name="admin_phone" value="{{ old('admin_phone', $admin?->phone) }}" class="form-control">
                            </div>

                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" value="1" id="admin_is_active" name="admin_is_active" {{ old('admin_is_active', $admin?->is_active ? '1' : '0') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="admin_is_active">
                                    Administrador activo
                                </label>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label class="form-label">Nueva contraseña <span class="text-muted">(opcional)</span></label>
                                <input type="password" name="admin_password" class="form-control" autocomplete="new-password">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirmar nueva contraseña</label>
                                <input type="password" name="admin_password_confirmation" class="form-control" autocomplete="new-password">
                            </div>

                            <div class="alert alert-light border mb-0">
                                Si dejas la contraseña vacía, se conservará la actual. Si la completas, el sistema la renovará al guardar.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-outline-dark rounded-pill px-4">
                            Guardar administrador inicial
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h5 class="mb-1">Estado del tenant</h5>
                        <p class="text-muted mb-0">Desde aquí puedes suspender o reactivar la organización sin alterar su entorno ni borrar configuración.</p>
                    </div>
                    <span class="badge {{ $organization->status === 'suspended' ? 'bg-danger' : 'bg-success' }}">
                        {{ strtoupper($organization->status) }}
                    </span>
                </div>

                <div class="alert alert-light border">
                    Suspender cambia el estado a <strong>SUSPENDED</strong>. Reactivar lo devuelve a <strong>ACTIVE</strong>. La organización default no puede suspenderse.
                </div>

                <div class="d-flex gap-3 justify-content-end">
                    @if($organization->status === 'suspended')
                        <form action="{{ route('admin.organizations.reactivate', $organization) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <button type="submit" class="btn btn-success rounded-pill px-4">
                                Reactivar tenant
                            </button>
                        </form>
                    @else
                        <form action="{{ route('admin.organizations.suspend', $organization) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <button type="submit" class="btn btn-outline-danger rounded-pill px-4" {{ $organization->is_default ? 'disabled' : '' }}>
                                Suspender tenant
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h5 class="mb-1">Preparación para producción</h5>
                        <p class="text-muted mb-0">Esta vista muestra qué validaciones mínimas faltan antes de habilitar la promoción desde <strong>DEMO</strong> a <strong>PRODUCTION</strong>.</p>
                    </div>
                    <div class="text-end">
                        <div class="mb-2">
                            <span class="badge {{ $allChecksOk ? 'bg-success' : 'bg-warning text-dark' }}">
                                {{ $allChecksOk ? 'LISTA PARA PRODUCCIÓN' : 'AÚN EN PREPARACIÓN' }}
                            </span>
                        </div>
                        @if($isProduction)
                            <span class="badge bg-success">PRODUCTION ACTIVO</span>
                        @elseif($allChecksOk)
                            <form action="{{ route('admin.organizations.activate-production', $organization) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="btn btn-success rounded-pill px-4" {{ $organization->status !== 'active' ? 'disabled' : '' }}>
                                    Activar en producción
                                </button>
                            </form>
                        @else
                            <span class="small text-muted">Completa los checks pendientes para habilitar la promoción.</span>
                        @endif
                    </div>
                </div>

                <div class="row g-3">
                    @foreach($productionChecks as $check)
                        <div class="col-md-6">
                            <div class="border rounded-4 p-3 h-100 {{ $check['ok'] ? 'border-success-subtle bg-success-subtle' : 'border-warning-subtle bg-warning-subtle' }}">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="fw-semibold">{{ $check['label'] }}</div>
                                        <div class="small text-muted">{{ $check['message'] }}</div>
                                    </div>
                                    <span class="badge {{ $check['ok'] ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ $check['ok'] ? 'OK' : 'PENDIENTE' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

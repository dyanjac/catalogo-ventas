@extends('layouts.auth')

@section('title', 'Registro SaaS')

@section('content')
<section class="auth-screen">
    <div class="auth-screen__backdrop"></div>

    <div class="auth-screen__content">
        <div class="auth-screen__panel auth-screen__panel--brand">
            <div class="auth-screen__brand-lockup">
                <div class="auth-screen__brand-logo auth-screen__brand-logo--fallback">
                    SA
                </div>

                <div>
                    <div class="auth-screen__eyebrow">Onboarding SaaS</div>
                    <div class="auth-screen__brand-name">Alta publica de organizaciones</div>
                    <div class="auth-screen__brand-meta">Provisionamiento inicial en entorno DEMO</div>
                </div>
            </div>

            <h1 class="auth-screen__title">Crea una empresa nueva sin iniciar sesion</h1>
            <p class="auth-screen__copy">
                Este flujo crea un tenant nuevo con sucursal principal, administrador inicial y configuracion base de comercio, facturacion y contabilidad.
            </p>

            <div class="auth-screen__card-grid">
                <div class="auth-screen__mini-card">
                    <div class="auth-screen__mini-label">Entorno inicial</div>
                    <div class="auth-screen__mini-value">DEMO</div>
                </div>
                <div class="auth-screen__mini-card">
                    <div class="auth-screen__mini-label">Acceso admin</div>
                    <div class="auth-screen__mini-value">Correo + password temporal</div>
                </div>
                <div class="auth-screen__mini-card">
                    <div class="auth-screen__mini-label">Produccion</div>
                    <div class="auth-screen__mini-value">Activacion separada</div>
                </div>
            </div>

            <div class="auth-screen__company-strip">
                <div class="auth-screen__company-chip">1. Organizacion y sucursal principal</div>
                <div class="auth-screen__company-chip">2. Admin inicial con credenciales nuevas</div>
                <div class="auth-screen__company-chip">3. La contrasena se muestra al finalizar el registro</div>
            </div>
        </div>

        <div class="auth-screen__panel auth-screen__panel--form">
            <div class="auth-screen__form-header">
                <div>
                    <div class="auth-screen__kicker">Alta publica</div>
                    <h2 class="auth-screen__form-title">Nueva organizacion DEMO</h2>
                </div>

                <a href="{{ route('admin.login') }}" class="btn btn-outline-secondary rounded-pill px-3 py-2">
                    Ir al login admin
                </a>
            </div>

            <div class="auth-form__alert" role="alert">
                El acceso administrativo inicial se crea automaticamente. Debes ingresar el <strong>nombre</strong> y el <strong>correo</strong> del administrador. La <strong>contrasena temporal</strong> se genera sola y se muestra al terminar el registro.
            </div>

            @if(session('success'))
                <div class="auth-form__alert" role="alert">
                    <div class="fw-semibold mb-2">{{ session('success') }}</div>
                    @if(session('provisioned_credentials'))
                        @php($credentials = session('provisioned_credentials'))
                        <div><strong>Organizacion:</strong> {{ $credentials['organization'] }}</div>
                        <div><strong>Usuario admin:</strong> {{ $credentials['admin_email'] }}</div>
                        <div><strong>Contrasena temporal:</strong> {{ $credentials['generated_password'] }}</div>
                        <div class="mt-3">
                            <a href="{{ $credentials['admin_login_url'] }}" class="btn btn-primary rounded-pill px-4">
                                Continuar al login administrativo
                            </a>
                        </div>
                    @endif
                </div>
            @endif

            @if($errors->any())
                <div class="auth-form__alert" role="alert">
                    Revisa los datos del formulario y corrige los campos marcados.
                </div>
            @endif

            <form method="POST" action="{{ route('saas.register.store') }}" class="auth-form">
                @csrf

                <div class="row g-3">
                    <div class="col-md-8 auth-form__field">
                        <label class="form-label">Nombre de organizacion</label>
                        <input type="text" name="organization_name" value="{{ old('organization_name') }}" class="form-control" required>
                        @error('organization_name')
                            <div class="auth-form__error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4 auth-form__field">
                        <label class="form-label">RUC / Tax ID</label>
                        <input type="text" name="tax_id" value="{{ old('tax_id') }}" class="form-control">
                        @error('tax_id')
                            <div class="auth-form__error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 auth-form__field">
                        <label class="form-label">Codigo interno</label>
                        <input type="text" name="organization_code" value="{{ old('organization_code') }}" class="form-control" required>
                        @error('organization_code')
                            <div class="auth-form__error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 auth-form__field">
                        <label class="form-label">Slug</label>
                        <input type="text" name="organization_slug" value="{{ old('organization_slug') }}" class="form-control" required>
                        @error('organization_slug')
                            <div class="auth-form__error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 auth-form__field">
                        <label class="form-label">Email comercial</label>
                        <input type="email" name="contact_email" value="{{ old('contact_email') }}" class="form-control" required>
                        @error('contact_email')
                            <div class="auth-form__error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 auth-form__field">
                        <label class="form-label">Telefono</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" class="form-control">
                        @error('phone')
                            <div class="auth-form__error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 auth-form__field">
                        <label class="form-label">Ciudad</label>
                        <input type="text" name="city" value="{{ old('city') }}" class="form-control">
                        @error('city')
                            <div class="auth-form__error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 auth-form__field">
                        <label class="form-label">Sucursal principal</label>
                        <input type="text" name="branch_name" value="{{ old('branch_name', 'Sucursal Principal') }}" class="form-control" required>
                        @error('branch_name')
                            <div class="auth-form__error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 auth-form__field">
                        <label class="form-label">Direccion</label>
                        <textarea name="address" class="form-control" rows="3">{{ old('address') }}</textarea>
                        @error('address')
                            <div class="auth-form__error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 auth-form__field">
                        <label class="form-label">Nombre del administrador inicial</label>
                        <input type="text" name="admin_name" value="{{ old('admin_name') }}" class="form-control" required>
                        <div class="auth-screen__footer-note mt-2">Este campo es el nombre de la persona, no el usuario de acceso.</div>
                        @error('admin_name')
                            <div class="auth-form__error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 auth-form__field">
                        <label class="form-label">Correo del administrador</label>
                        <input type="email" name="admin_email" value="{{ old('admin_email') }}" class="form-control" required>
                        <div class="auth-screen__footer-note mt-2">Este correo sera el usuario para entrar a <strong>/admin/login</strong>.</div>
                        @error('admin_email')
                            <div class="auth-form__error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 auth-form__field">
                        <label class="form-label">Telefono del administrador</label>
                        <input type="text" name="admin_phone" value="{{ old('admin_phone') }}" class="form-control">
                        @error('admin_phone')
                            <div class="auth-form__error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 auth-form__submit mt-3">
                    Crear organizacion demo
                </button>
            </form>

            <div class="auth-divider">
                <span>Acceso</span>
            </div>

            <div class="auth-provider-list">
                <a href="{{ route('admin.login') }}" class="btn btn-outline-secondary w-100 justify-content-start">
                    Ya tengo credenciales administrativas
                </a>
                <a href="{{ route('login') }}" class="btn btn-outline-secondary w-100 justify-content-start">
                    Ir al login del ecommerce
                </a>
            </div>

            <div class="auth-screen__footer-note">
                El tenant se crea siempre en DEMO. La promocion a PRODUCTION continua siendo una accion posterior y controlada.
            </div>
        </div>
    </div>
</section>
@endsection

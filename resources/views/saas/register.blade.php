@extends('layouts.marketing')

@section('title', 'Crea tu organización | '.config('marketing.brand_name'))
@section('description', 'Crea una organización en entorno DEMO y recibe el acceso de su administrador inicial.')
@section('canonical', route('saas.register.create'))
@section('robots', 'noindex, follow')

@section('content')
<section class="marketing-onboarding">
    <div class="marketing-container marketing-onboarding__grid">
        <aside class="marketing-onboarding__intro" aria-labelledby="registration-title">
            <a class="marketing-text-link" href="{{ route('home') }}">← Volver a la plataforma</a>
            <p class="marketing-eyebrow">Tu primer paso</p>
            <h1 id="registration-title">Un espacio para toda tu operación.</h1>
            <p>Crea tu organización de prueba y comienza a configurar tu empresa en {{ config('marketing.brand_name') }}.</p>
            <ul class="marketing-onboarding__benefits">
                <li><strong>Tu empresa y sucursal principal</strong><span>Un punto de partida para organizar tu trabajo.</span></li>
                <li><strong>Un administrador inicial</strong><span>Recibirás las credenciales al completar el registro.</span></li>
                <li><strong>Un entorno DEMO</strong><span>El paso a producción se realiza posteriormente.</span></li>
            </ul>
            <p class="marketing-onboarding__login">¿Ya tienes una cuenta? <a href="{{ route('admin.login') }}">Ingresar al ERP</a></p>
        </aside>

        <div class="marketing-registration">
            @if(session('provisioned_credentials'))
                @php($credentials = session('provisioned_credentials'))
                <div class="marketing-registration__success" role="status">
                    <p class="marketing-eyebrow">Organización creada</p>
                    <h2>Tu espacio DEMO está listo.</h2>
                    <p>Guarda las credenciales iniciales antes de continuar.</p>
                    <dl class="marketing-credentials">
                        <dt>Organización</dt><dd>{{ $credentials['organization'] }}</dd>
                        <dt>Correo del administrador</dt><dd>{{ $credentials['admin_email'] }}</dd>
                        <dt>Contraseña temporal</dt><dd><code>{{ $credentials['generated_password'] }}</code></dd>
                    </dl>
                    <a class="marketing-button" href="{{ $credentials['admin_login_url'] }}">Ingresar a mi organización</a>
                </div>
            @else
                <div class="marketing-registration__heading">
                    <span class="marketing-registration__badge">DEMO</span>
                    <h2>Crea tu organización</h2>
                    <p>Los campos con <span aria-hidden="true">*</span> son obligatorios.</p>
                </div>

                @if($errors->any())
                    <div class="marketing-form-alert" role="alert" tabindex="-1" id="registration-errors">
                        <strong>Revisa los datos indicados para continuar.</strong>
                        <ul>
                            @foreach($errors->messages() as $field => $messages)
                                <li><a href="#registration-{{ $field }}">{{ $messages[0] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('saas.register.store') }}" class="marketing-form" data-analytics-event="Registration Submitted" data-analytics-placement="registration-form">
                    @csrf
                    <fieldset>
                        <legend><span aria-hidden="true">01</span> Tu empresa</legend>
                        <div class="marketing-form__grid">
                            <x-marketing.form-field name="organization_name" label="Nombre de la organización" required maxlength="160" autocomplete="organization" />
                            <x-marketing.form-field name="contact_email" label="Correo comercial" type="email" required maxlength="255" autocomplete="section-company email" />
                            <x-marketing.form-field name="organization_code" label="Código de la organización" required maxlength="40" hint="Un identificador único, por ejemplo ACME. Usa letras, números, guiones o guiones bajos." />
                            <x-marketing.form-field name="organization_slug" label="Nombre en la dirección web" required maxlength="160" hint="Por ejemplo: mi-comercio. Identifica a tu organización y su tienda cuando el canal ecommerce esté habilitado." />
                            <x-marketing.form-field name="branch_name" label="Sucursal principal" required maxlength="120" default="Sucursal Principal" />
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend><span aria-hidden="true">02</span> Administrador inicial</legend>
                        <p class="marketing-form__help">La contraseña temporal se genera y se muestra al finalizar el registro.</p>
                        <div class="marketing-form__grid">
                            <x-marketing.form-field name="admin_name" label="Nombre completo" required maxlength="120" autocomplete="section-admin name" />
                            <x-marketing.form-field name="admin_email" label="Correo para ingresar al ERP" type="email" required maxlength="255" autocomplete="section-admin email" />
                        </div>
                    </fieldset>

                    <details class="marketing-form__optional" @if($errors->any()) open @endif>
                        <summary>Marca y datos de contacto <span>Opcional</span></summary>
                        <div class="marketing-form__grid">
                            <x-marketing.form-field name="brand_name" label="Nombre de marca" maxlength="160" hint="Si lo dejas vacío, usaremos el nombre de la organización." />
                            <x-marketing.form-field name="tagline" label="Frase de tu marca" maxlength="255" />
                            <x-marketing.form-field name="tax_id" label="RUC / Identificación fiscal" maxlength="30" />
                            <x-marketing.form-field name="phone" label="Teléfono comercial" type="tel" maxlength="30" autocomplete="section-company tel" />
                            <x-marketing.form-field name="support_email" label="Correo de soporte" type="email" maxlength="255" />
                            <x-marketing.form-field name="support_phone" label="Teléfono de soporte" type="tel" maxlength="30" />
                            <x-marketing.form-field name="city" label="Ciudad" maxlength="100" autocomplete="address-level2" />
                            <x-marketing.form-field name="address" label="Dirección" maxlength="200" autocomplete="street-address" />
                            <x-marketing.form-field name="admin_phone" label="Teléfono del administrador" type="tel" maxlength="30" autocomplete="section-admin tel" />
                        </div>
                    </details>

                    <div class="marketing-form__submit">
                        <button type="submit" class="marketing-button">Crear organización DEMO</button>
                        <p>El registro crea un entorno de prueba. La activación de producción es posterior.</p>
                    </div>
                </form>
            @endif
        </div>
    </div>
</section>
@endsection

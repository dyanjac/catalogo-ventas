<section class="marketing-onboarding">
    <div class="marketing-container marketing-onboarding__grid marketing-login">
        <div class="marketing-onboarding__intro">
            <a class="marketing-text-link" href="{{ route('home') }}">← Volver a la plataforma</a>
            <p class="marketing-eyebrow">Acceso al ERP</p>
            @if($resolvedOrganization)
                <div class="marketing-login__brand">
                    @if($loginBrand['logo_url'])
                        <img src="{{ $loginBrand['logo_url'] }}" alt="{{ $loginBrand['brand_name'] }}">
                    @endif
                    <strong>{{ $loginBrand['brand_name'] }}</strong>
                </div>
                <h1>{{ ($authSettings['login_headline'] ?? '') ?: 'Continúa con tu organización.' }}</h1>
                <p>{{ $loginBrand['tagline'] ?: (($authSettings['login_slogan'] ?? '') ?: 'Ingresa con tu cuenta para continuar con la operación de tu empresa.') }}</p>
                @if($loginBrand['support_email'] || $loginBrand['support_phone'])
                    <div class="marketing-login__support">
                        <strong>Contacto de tu organización</strong>
                        @if($loginBrand['support_email'])<span>{{ $loginBrand['support_email'] }}</span>@endif
                        @if($loginBrand['support_phone'])<span>{{ $loginBrand['support_phone'] }}</span>@endif
                    </div>
                @endif
            @else
                <h1>Tu equipo. Tu organización. Un solo acceso.</h1>
                <p>Ingresa tu correo para encontrar la organización a la que perteneces. Si tienes acceso a más de una, podrás elegir con cuál trabajar.</p>
                <p class="marketing-onboarding__login">¿Aún no tienes una organización? <a href="{{ route('saas.register.create') }}">Crea un espacio DEMO</a>.</p>
            @endif
        </div>

        <div class="marketing-registration">
            <div class="marketing-registration__heading">
                <span class="marketing-registration__badge">Paso {{ $resolvedOrganization ? '2 de 2' : '1 de 2' }}</span>
                <h2>{{ $resolvedOrganization ? 'Inicia sesión' : 'Encuentra tu organización' }}</h2>
                <p>{{ $resolvedOrganization ? 'Utiliza las credenciales de tu cuenta administrativa.' : 'Comienza con el correo de tu cuenta administrativa.' }}</p>
            </div>

            @if(session('error'))
                <div class="marketing-form-alert" role="alert">{{ session('error') }}</div>
            @endif

            <form wire:submit="{{ $resolvedOrganization ? 'login' : 'identifyOrganization' }}" class="marketing-form">
                <div class="marketing-form__field">
                    <label for="admin-login-identifier">{{ $resolvedOrganization && !empty($authSettings['ldap_enabled']) ? 'Correo o usuario de directorio' : 'Correo electrónico' }}</label>
                    <input wire:model="identifier" id="admin-login-identifier" type="text" autocomplete="username" required maxlength="255"
                        @error('identifier') aria-invalid="true" aria-describedby="admin-login-identifier-error" @enderror>
                    @error('identifier')<p id="admin-login-identifier-error" class="marketing-form__error" role="alert">{{ $message }}</p>@enderror
                </div>

                @error('selectedOrganizationSlug')
                    <p class="marketing-form__error" role="alert">{{ $message }}</p>
                @enderror

                @if(!$resolvedOrganization)
                    @if($organizationOptions !== [])
                        <div class="marketing-login__organizations" aria-label="Elige tu organización">
                            @foreach($organizationOptions as $option)
                                <button type="button" wire:key="organization-{{ $option['slug'] }}"
                                    wire:click="selectOrganization(@js($option['slug']))"
                                    wire:loading.attr="disabled" class="marketing-login__organization">
                                    <strong>{{ $option['name'] }}</strong>
                                    <span>{{ $option['code'] }} · {{ $option['slug'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                    <button type="submit" class="marketing-button marketing-login__submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="identifyOrganization">Continuar</span>
                        <span wire:loading wire:target="identifyOrganization" role="status">Buscando organización…</span>
                    </button>
                @else
                    <div class="marketing-login__selection">
                        <div><span>Organización seleccionada</span><strong>{{ $resolvedOrganization->name }}</strong></div>
                        <button type="button" wire:click="clearOrganizationSelection" wire:loading.attr="disabled">Cambiar</button>
                    </div>
                    <div class="marketing-form__field">
                        <label for="admin-login-password">Contraseña</label>
                        <input wire:model="password" id="admin-login-password" type="password" autocomplete="current-password" required
                            @error('password') aria-invalid="true" aria-describedby="admin-login-password-error" @enderror>
                        @error('password')<p id="admin-login-password-error" class="marketing-form__error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <label class="marketing-login__remember"><input wire:model="remember" type="checkbox"> Recordarme en este dispositivo</label>
                    <button type="submit" class="marketing-button marketing-login__submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="login">Ingresar al ERP</span>
                        <span wire:loading wire:target="login" role="status">Validando acceso…</span>
                    </button>
                @endif
                <p wire:offline class="marketing-form__error" role="status">Se perdió la conexión. Intenta continuar cuando vuelva a estar disponible.</p>
            </form>
        </div>
    </div>
</section>

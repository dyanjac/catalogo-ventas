<section class="auth-screen">
    <div class="auth-screen__backdrop"></div>

    <div class="auth-screen__content">
        <div class="auth-screen__panel auth-screen__panel--brand">
            <div class="auth-screen__brand-lockup">
                @if(!empty($commerce['logo_url']))
                    <div class="auth-screen__brand-logo">
                        <img src="{{ $commerce['logo_url'] }}" alt="{{ $commerce['name'] ?? 'Empresa' }}">
                    </div>
                @else
                    <div class="auth-screen__brand-logo auth-screen__brand-logo--fallback">
                        {{ $resolvedOrganization ? \Illuminate\Support\Str::of($commerce['name'] ?? 'OR')->explode(' ')->filter()->take(2)->map(fn ($segment) => \Illuminate\Support\Str::substr($segment, 0, 1))->implode('') : 'ID' }}
                    </div>
                @endif

                <div>
                    <div class="auth-screen__eyebrow">Acceso administrativo</div>
                    <div class="auth-screen__brand-name">{{ $commerce['name'] ?? 'Panel administrativo' }}</div>
                    @if($resolvedOrganization)
                        <div class="auth-screen__brand-meta">{{ $resolvedOrganization->code }} · {{ $resolvedOrganization->slug }}</div>
                    @else
                        <div class="auth-screen__brand-meta">Primero identifica la organización antes de autenticar la cuenta.</div>
                    @endif
                </div>
            </div>

            <h1 class="auth-screen__title">
                {{ $resolvedOrganization ? ($authSettings['login_headline'] ?? 'Ingreso seguro para operacion interna') : 'Selecciona tu organizacion antes de ingresar' }}
            </h1>
            <p class="auth-screen__copy">
                {{ $resolvedOrganization ? ($authSettings['login_slogan'] ?? 'Este acceso toma la identidad visual configurada en el panel y permanece separado del login del ecommerce.') : 'Si tu correo existe en una sola organización, la resolveremos automáticamente. Si existe en varias, podrás elegir la correcta antes de validar contraseña, LDAP u otros proveedores.' }}
            </p>

            <div class="auth-screen__card-grid">
                <div class="auth-screen__mini-card">
                    <div class="auth-screen__mini-label">Organizacion</div>
                    <div class="auth-screen__mini-value">{{ $resolvedOrganization ? 'Identificada' : 'Pendiente' }}</div>
                </div>
                <div class="auth-screen__mini-card">
                    <div class="auth-screen__mini-label">Federacion</div>
                    <div class="auth-screen__mini-value">
                        {{ $resolvedOrganization && (!empty($authSettings['oauth_google_enabled']) || !empty($authSettings['oauth_github_enabled']) || !empty($authSettings['oauth_custom_enabled'])) ? 'Proveedores preparados' : 'Pendiente' }}
                    </div>
                </div>
                <div class="auth-screen__mini-card">
                    <div class="auth-screen__mini-label">Directorio</div>
                    <div class="auth-screen__mini-value">{{ $resolvedOrganization && !empty($authSettings['ldap_enabled']) ? 'LDAP activo' : 'Sin contexto' }}</div>
                </div>
            </div>

            <div class="auth-screen__company-strip">
                @if($resolvedOrganization && !empty($commerce['phone']))
                    <div class="auth-screen__company-chip">Tel: {{ $commerce['phone'] }}</div>
                @endif
                @if($resolvedOrganization && !empty($commerce['tax_id']))
                    <div class="auth-screen__company-chip">RUC: {{ $commerce['tax_id'] }}</div>
                @endif
                @if($resolvedOrganization)
                    <div class="auth-screen__company-chip">Tenant: {{ $resolvedOrganization->slug }}</div>
                @endif
            </div>
        </div>

        <div class="auth-screen__panel auth-screen__panel--form">
            <div class="auth-screen__form-header">
                <div>
                    <div class="auth-screen__kicker">Iniciar sesion</div>
                    <h2 class="auth-screen__form-title">{{ $resolvedOrganization ? ($commerce['name'] ?? 'Panel administrativo') : 'Identificar organizacion' }}</h2>
                </div>

                <flux:badge color="zinc">-V.1.1-</flux:badge>
            </div>

            @if(session('error'))
                <div class="auth-form__alert" role="alert">{{ session('error') }}</div>
            @endif

            <form wire:submit="login" class="auth-form">
                @error('identifier')
                    <div class="auth-form__alert" role="alert">{{ $message }}</div>
                @enderror
                @error('selectedOrganizationSlug')
                    <div class="auth-form__alert" role="alert">{{ $message }}</div>
                @enderror

                <div class="auth-form__field">
                    <label class="form-label" for="admin-login-identifier">Correo o usuario</label>
                    <flux:input
                        wire:model.live="identifier"
                        id="admin-login-identifier"
                        type="text"
                        placeholder="admin@empresa.com o usuario"
                        autofocus
                    />
                </div>

                @if(!$resolvedOrganization)
                    <flux:button type="button" variant="primary" class="w-full justify-center auth-form__submit" wire:click="identifyOrganization">
                        Identificar organizacion
                    </flux:button>

                    @if($organizationOptions !== [])
                        <div class="auth-form__field">
                            <label class="form-label">Organizaciones encontradas</label>
                            <div class="d-grid gap-2">
                                @foreach($organizationOptions as $option)
                                    <button
                                        type="button"
                                        wire:click="selectOrganization('{{ $option['slug'] }}')"
                                        class="btn {{ $selectedOrganizationSlug === $option['slug'] ? 'btn-primary' : 'btn-outline-secondary' }} text-start"
                                    >
                                        <strong>{{ $option['name'] }}</strong><br>
                                        <span class="small opacity-75">{{ $option['code'] }} · {{ $option['slug'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    <div class="auth-form__alert" role="alert">
                        Organización seleccionada: <strong>{{ $resolvedOrganization->name }}</strong>
                        <button type="button" wire:click="clearOrganizationSelection" class="btn btn-sm btn-outline-secondary ms-3">Cambiar</button>
                    </div>

                    <div class="auth-form__field">
                        <label class="form-label" for="admin-login-password">Contrasena</label>
                        <flux:input wire:model.live="password" id="admin-login-password" type="password" placeholder="Ingresa tu contrasena" />
                        @error('password')
                            <div class="auth-form__error">{{ $message }}</div>
                        @enderror
                    </div>

                    <label class="auth-form__remember">
                        <input wire:model.live="remember" type="checkbox">
                        <span>Recordarme en este dispositivo</span>
                    </label>

                    <flux:button type="submit" variant="primary" class="w-full justify-center auth-form__submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="login">Ingresar al panel</span>
                        <span wire:loading wire:target="login">Validando acceso...</span>
                    </flux:button>

                    <div class="auth-divider">
                        <span>Proximamente</span>
                    </div>

                    <div class="auth-provider-list">
                        <flux:button variant="outline" class="w-full justify-start" :disabled="empty($authSettings['oauth_google_enabled'])" icon="globe-alt">
                            Google Workspace {{ !empty($authSettings['oauth_google_enabled']) ? '(configurado)' : '(pendiente)' }}
                        </flux:button>
                        <flux:button variant="outline" class="w-full justify-start" :disabled="empty($authSettings['oauth_github_enabled'])" icon="code-bracket">
                            GitHub Enterprise {{ !empty($authSettings['oauth_github_enabled']) ? '(configurado)' : '(pendiente)' }}
                        </flux:button>
                        <flux:button variant="outline" class="w-full justify-start" :disabled="empty($authSettings['ldap_enabled'])" icon="building-office-2">
                            LDAP / Active Directory {{ !empty($authSettings['ldap_enabled']) ? '(configurado)' : '(pendiente)' }}
                        </flux:button>
                    </div>
                @endif
            </form>

            <div class="auth-screen__footer-note">
                El acceso del cliente ecommerce permanece separado y sigue operando desde el flujo publico.
            </div>
        </div>
    </div>
</section>

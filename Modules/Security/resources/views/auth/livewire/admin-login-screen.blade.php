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
                        {{ \Illuminate\Support\Str::of($commerce['name'] ?? 'MP')->explode(' ')->filter()->take(2)->map(fn ($segment) => \Illuminate\Support\Str::substr($segment, 0, 1))->implode('') ?: 'MP' }}
                    </div>
                @endif

                <div>
                    <div class="auth-screen__eyebrow">Acceso administrativo</div>
                    <div class="auth-screen__brand-name">{{ $commerce['name'] ?? 'Panel administrativo' }}</div>
                    @if(!empty($commerce['email']))
                        <div class="auth-screen__brand-meta">{{ $commerce['email'] }}</div>
                    @endif
                </div>
            </div>

            <h1 class="auth-screen__title">Ingreso seguro para operacion interna</h1>
            <p class="auth-screen__copy">
                Este acceso toma la identidad visual configurada en el panel y permanece separado del login del ecommerce.
                Sera la base del modulo Security para autenticacion administrativa, control de roles, LDAP configurable
                y proveedores OAuth externos.
            </p>

            <div class="auth-screen__card-grid">
                <div class="auth-screen__mini-card">
                    <div class="auth-screen__mini-label">Canal</div>
                    <div class="auth-screen__mini-value">Panel admin</div>
                </div>
                <div class="auth-screen__mini-card">
                    <div class="auth-screen__mini-label">Federacion</div>
                    <div class="auth-screen__mini-value">Google y GitHub</div>
                </div>
                <div class="auth-screen__mini-card">
                    <div class="auth-screen__mini-label">Directorio</div>
                    <div class="auth-screen__mini-value">LDAP configurable</div>
                </div>
            </div>

            <div class="auth-screen__company-strip">
                @if(!empty($commerce['phone']))
                    <div class="auth-screen__company-chip">Tel: {{ $commerce['phone'] }}</div>
                @endif
                @if(!empty($commerce['tax_id']))
                    <div class="auth-screen__company-chip">RUC: {{ $commerce['tax_id'] }}</div>
                @endif
            </div>
        </div>

        <div class="auth-screen__panel auth-screen__panel--form">
            <div class="auth-screen__form-header">
                <div>
                    <div class="auth-screen__kicker">Iniciar sesion</div>
                    <h2 class="auth-screen__form-title">{{ $commerce['name'] ?? 'Panel administrativo' }}</h2>
                </div>

                <flux:badge color="zinc">Flux + Livewire</flux:badge>
            </div>

            <form wire:submit="login" class="auth-form">
                <div class="auth-form__field">
                    <label class="form-label" for="admin-login-email">Correo</label>
                    <flux:input wire:model.live="email" id="admin-login-email" type="email" placeholder="admin@empresa.com" autofocus />
                    @error('email')
                        <div class="auth-form__error">{{ $message }}</div>
                    @enderror
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

                <flux:button type="submit" variant="primary" class="w-full justify-center" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="login">Ingresar al panel</span>
                    <span wire:loading wire:target="login">Validando acceso...</span>
                </flux:button>
            </form>

            <div class="auth-divider">
                <span>Proximamente</span>
            </div>

            <div class="auth-provider-list">
                <flux:button variant="outline" class="w-full justify-start" disabled icon="globe-alt">
                    Google Workspace
                </flux:button>
                <flux:button variant="outline" class="w-full justify-start" disabled icon="code-bracket">
                    GitHub Enterprise
                </flux:button>
                <flux:button variant="outline" class="w-full justify-start" disabled icon="building-office-2">
                    LDAP / Active Directory
                </flux:button>
            </div>

            <div class="auth-screen__footer-note">
                El acceso del cliente ecommerce permanece separado y sigue operando desde el flujo publico.
            </div>
        </div>
    </div>
</section>

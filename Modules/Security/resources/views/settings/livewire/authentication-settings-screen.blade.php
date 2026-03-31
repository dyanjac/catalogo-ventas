<div class="security-settings-page">
    <x-admin.page-header
        eyebrow="Security"
        title="Authentication settings"
        description="Centraliza la configuracion del acceso administrativo, sesiones, LDAP y proveedores OAuth del panel."
    >
        <div class="flex gap-2">
            <flux:button href="{{ route('admin.security.audit.index') }}" variant="outline" icon="document-text">
                Ver auditoria
            </flux:button>
            <flux:button href="{{ route('admin.login') }}" variant="outline" icon="arrow-right-end-on-rectangle">
                Ver login admin
            </flux:button>
        </div>
    </x-admin.page-header>

    <div class="security-settings-alert" data-tone="info">
        Estas configurando el branding y los providers de autenticacion para la organizacion actual:
        <strong>{{ $organizationName ?: 'Sin contexto organizacional' }}</strong>
    </div>

    @if($statusMessage)
        <div class="security-settings-alert" data-tone="{{ $statusTone }}">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="security-settings-grid">
        <section class="security-card security-card--hero">
            <div class="security-card__header">
                <div>
                    <div class="security-card__eyebrow">Configuration</div>
                    <h3 class="security-card__title">Parametros base del acceso</h3>
                </div>
                <flux:badge color="sky">Tenant actual</flux:badge>
            </div>

            <div class="security-form-grid security-form-grid--2">
                <label class="security-field">
                    <span class="security-field__label">Session lifetime</span>
                    <flux:select wire:model.live="form.session_lifetime_hours">
                        @foreach([1,2,4,8,12,24] as $hour)
                            <option value="{{ $hour }}">{{ $hour }} {{ $hour === 1 ? 'hour' : 'hours' }}</option>
                        @endforeach
                    </flux:select>
                </label>

                <label class="security-field">
                    <span class="security-field__label">Password minimum length</span>
                    <flux:input type="number" min="8" max="64" wire:model.live="form.password_min_length" />
                </label>
            </div>

            <div class="security-switch-grid">
                <label class="security-switch">
                    <span><strong>Automatic user provisioning</strong><small>Crea usuarios administrativos al aceptar un proveedor externo.</small></span>
                    <flux:switch wire:model.live="form.auto_user_provisioning" />
                </label>
                <label class="security-switch">
                    <span><strong>Use SSO</strong><small>Activa experiencia de federacion cuando OAuth este operativo.</small></span>
                    <flux:switch wire:model.live="form.sso_enabled" />
                </label>
                <label class="security-switch">
                    <span><strong>Hide internal prompt</strong><small>Oculta el acceso interno cuando el negocio opere solo con SSO.</small></span>
                    <flux:switch wire:model.live="form.hide_internal_prompt" />
                </label>
            </div>
        </section>

        <section class="security-card">
            <div class="security-card__header">
                <div>
                    <div class="security-card__eyebrow">Authentication method</div>
                    <h3 class="security-card__title">Metodo principal</h3>
                </div>
            </div>

            <div class="security-option-grid">
                @foreach([
                    'internal' => ['title' => 'Internal', 'copy' => 'Credenciales locales del panel.', 'icon' => 'key'],
                    'ldap' => ['title' => 'LDAP', 'copy' => 'Directorio centralizado con bind configurable.', 'icon' => 'building-library'],
                    'oauth' => ['title' => 'OAuth', 'copy' => 'Federacion con Google, GitHub o proveedor custom.', 'icon' => 'globe-alt'],
                ] as $method => $meta)
                    <button
                        type="button"
                        class="security-option-card"
                        data-active="{{ $form['auth_method'] === $method ? 'true' : 'false' }}"
                        wire:click="chooseAuthMethod('{{ $method }}')"
                    >
                        <span class="security-option-card__icon">
                            <flux:icon :name="$meta['icon']" />
                        </span>
                        <span class="security-option-card__title">{{ $meta['title'] }}</span>
                        <span class="security-option-card__copy">{{ $meta['copy'] }}</span>
                    </button>
                @endforeach
            </div>
        </section>
    </div>

    <div class="security-settings-grid">
        <section class="security-card">
            <div class="security-card__header">
                <div>
                    <div class="security-card__eyebrow">Login experience</div>
                    <h3 class="security-card__title">Mensaje corporativo del login admin</h3>
                </div>
            </div>

            <div class="security-form-grid">
                <label class="security-field">
                    <span class="security-field__label">Headline</span>
                    <flux:input wire:model.live="form.login_headline" />
                </label>

                <label class="security-field">
                    <span class="security-field__label">Slogan</span>
                    <flux:textarea rows="3" wire:model.live="form.login_slogan" />
                </label>
            </div>
        </section>

        <section class="security-card">
            <div class="security-card__header">
                <div>
                    <div class="security-card__eyebrow">Current behavior</div>
                    <h3 class="security-card__title">Resumen operativo</h3>
                </div>
            </div>

            <div class="security-stat-list">
                <div class="security-stat"><span>Metodo activo</span><strong>{{ strtoupper($form['auth_method'] ?? 'internal') }}</strong></div>
                <div class="security-stat"><span>Google</span><strong>{{ !empty($form['oauth_google_enabled']) ? 'Enabled' : 'Disabled' }}</strong></div>
                <div class="security-stat"><span>GitHub</span><strong>{{ !empty($form['oauth_github_enabled']) ? 'Enabled' : 'Disabled' }}</strong></div>
                <div class="security-stat"><span>LDAP</span><strong>{{ !empty($form['ldap_enabled']) ? 'Enabled' : 'Disabled' }}</strong></div>
            </div>
        </section>
    </div>

    <section class="security-card">
        <div class="security-card__header">
            <div>
                <div class="security-card__eyebrow">OAuth / External providers</div>
                <h3 class="security-card__title">Federacion y proveedores sociales</h3>
            </div>
        </div>

        <div class="security-option-grid security-option-grid--providers">
            @foreach([
                'google' => ['title' => 'Google', 'copy' => 'Workspace o cuentas Google corporativas.', 'toggle' => 'oauth_google_enabled'],
                'github' => ['title' => 'GitHub', 'copy' => 'GitHub o GitHub Enterprise para equipos tecnicos.', 'toggle' => 'oauth_github_enabled'],
                'custom' => ['title' => 'Custom', 'copy' => 'OIDC/OAuth con endpoints personalizados.', 'toggle' => 'oauth_custom_enabled'],
            ] as $provider => $meta)
                <button
                    type="button"
                    class="security-option-card"
                    data-active="{{ $form['oauth_provider'] === $provider ? 'true' : 'false' }}"
                    wire:click="chooseOauthProvider('{{ $provider }}')"
                >
                    <span class="security-option-card__title">{{ $meta['title'] }}</span>
                    <span class="security-option-card__copy">{{ $meta['copy'] }}</span>
                    <span class="security-option-card__toggle">
                        <flux:switch wire:model.live="form.{{ $meta['toggle'] }}" />
                    </span>
                </button>
            @endforeach
        </div>

        <div class="security-form-grid security-form-grid--2">
            <label class="security-field"><span class="security-field__label">Client ID</span><flux:input wire:model.live="form.oauth_client_id" /></label>
            <label class="security-field"><span class="security-field__label">Client secret</span><flux:input type="password" wire:model.live="form.oauth_client_secret" /></label>
            <label class="security-field"><span class="security-field__label">Authorization URL</span><flux:input wire:model.live="form.oauth_authorization_url" placeholder="https://example.com/oauth/authorize" /></label>
            <label class="security-field"><span class="security-field__label">Access token URL</span><flux:input wire:model.live="form.oauth_token_url" placeholder="https://example.com/oauth/token" /></label>
            <label class="security-field"><span class="security-field__label">Resource URL</span><flux:input wire:model.live="form.oauth_resource_url" placeholder="https://example.com/userinfo" /></label>
            <label class="security-field"><span class="security-field__label">Redirect URL</span><flux:input wire:model.live="form.oauth_redirect_url" placeholder="https://tu-dominio/admin/auth/callback" /></label>
            <label class="security-field"><span class="security-field__label">Logout URL</span><flux:input wire:model.live="form.oauth_logout_url" /></label>
            <label class="security-field">
                <span class="security-field__label">Auth style</span>
                <flux:select wire:model.live="form.oauth_auth_style">
                    <option value="auto">Auto detect</option>
                    <option value="basic">HTTP Basic</option>
                    <option value="request_body">Request body</option>
                </flux:select>
            </label>
            <label class="security-field"><span class="security-field__label">User identifier</span><flux:input wire:model.live="form.oauth_user_identifier" /></label>
            <label class="security-field"><span class="security-field__label">Scopes</span><flux:input wire:model.live="form.oauth_scopes" placeholder="openid,email,profile" /></label>
        </div>

        <label class="security-switch security-switch--inline">
            <span><strong>Automatic team membership</strong><small>Preparado para mapear claims externos a roles internos.</small></span>
            <flux:switch wire:model.live="form.oauth_auto_team_membership" />
        </label>
    </section>

    <section class="security-card">
        <div class="security-card__header">
            <div>
                <div class="security-card__eyebrow">LDAP configuration</div>
                <h3 class="security-card__title">Directorio, grupos y mapeo a roles internos</h3>
            </div>
        </div>

        <div class="security-switch-grid security-switch-grid--4">
            <label class="security-switch"><span><strong>Enable LDAP</strong><small>Activa autenticacion delegada al directorio.</small></span><flux:switch wire:model.live="form.ldap_enabled" /></label>
            <label class="security-switch"><span><strong>Anonymous bind</strong><small>Cuando esta activo, ignora Reader DN y Reader password.</small></span><flux:switch wire:model.live="form.ldap_anonymous" /></label>
            <label class="security-switch"><span><strong>StartTLS</strong><small>Negocia cifrado sobre conexion LDAP tradicional.</small></span><flux:switch wire:model.live="form.ldap_use_starttls" /></label>
            <label class="security-switch"><span><strong>TLS</strong><small>Conecta mediante canal seguro dedicado.</small></span><flux:switch wire:model.live="form.ldap_use_tls" /></label>
        </div>

        <div class="security-form-grid security-form-grid--2">
            <label class="security-field"><span class="security-field__label">LDAP host</span><flux:input wire:model.live="form.ldap_host" placeholder="172.16.0.114" /></label>
            <label class="security-field"><span class="security-field__label">Port</span><flux:input type="number" wire:model.live="form.ldap_port" /></label>
            <label class="security-field"><span class="security-field__label">Reader DN</span><flux:input wire:model.live="form.ldap_bind_dn" placeholder="uid=zimbra,cn=admins,cn=zimbra" /></label>
            <label class="security-field"><span class="security-field__label">Reader password</span><flux:input type="password" wire:model.live="form.ldap_bind_password" /></label>
            <label class="security-field"><span class="security-field__label">Base DN</span><flux:input wire:model.live="form.ldap_base_dn" placeholder="ou=people,dc=close2u,dc=pe" /></label>
            <label class="security-field"><span class="security-field__label">Username attribute</span><flux:input wire:model.live="form.ldap_username_attribute" placeholder="uid" /></label>
            <label class="security-field"><span class="security-field__label">User filter</span><flux:input wire:model.live="form.ldap_user_filter" placeholder="(&(objectClass=inetOrgPerson)(uid=%s))" /></label>
            <label class="security-field"><span class="security-field__label">Email attribute</span><flux:input wire:model.live="form.ldap_email_attribute" placeholder="mail" /></label>
            <label class="security-field">
                <span class="security-field__label">Verify certificate</span>
                <flux:select wire:model.live="form.ldap_verify_certificate">
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </flux:select>
            </label>
            <label class="security-field"><span class="security-field__label">Group base DN</span><flux:input wire:model.live="form.ldap_group_base_dn" placeholder="ou=groups,dc=close2u,dc=pe" /></label>
            <label class="security-field"><span class="security-field__label">Group filter</span><flux:input wire:model.live="form.ldap_group_filter" placeholder="(objectClass=groupOfNames)" /></label>
            <label class="security-field"><span class="security-field__label">Group membership attribute</span><flux:input wire:model.live="form.ldap_group_membership_attribute" placeholder="member" /></label>
            <label class="security-field"><span class="security-field__label">Admin groups</span><flux:input wire:model.live="form.ldap_admin_group_names" placeholder="admins,erp-admins,security-ops" /></label>
            <label class="security-field"><span class="security-field__label">Fallback email domain</span><flux:input wire:model.live="form.ldap_fallback_email_domain" placeholder="ldap.local" /></label>
        </div>

        <label class="security-field">
            <span class="security-field__label">LDAP group -> role map</span>
            <flux:textarea rows="5" wire:model.live="form.ldap_group_role_map" placeholder="sales-cashiers=sales_cashier&#10;billing-team=billing_manager&#10;catalog-team=catalog_manager" />
        </label>

        <div class="security-settings-alert" data-tone="info">
            Usa <code>%s</code> dentro del filtro para insertar el identificador del login. Para mapear grupos LDAP a roles internos, usa una linea por grupo con formato <code>grupo=rol_1,rol_2</code>.
        </div>

        <label class="security-switch security-switch--inline">
            <span><strong>Assign admin by group</strong><small>Activa una validacion rapida de grupos administrativos ademas del mapeo detallado de roles.</small></span>
            <flux:switch wire:model.live="form.ldap_assign_admin_by_group" />
        </label>

        <div class="security-card__header">
            <div>
                <div class="security-card__eyebrow">LDAP connectivity test</div>
                <h3 class="security-card__title">Prueba de bind y busqueda</h3>
            </div>
        </div>

        <div class="security-form-grid security-form-grid--2">
            <label class="security-field"><span class="security-field__label">Test identifier</span><flux:input wire:model.live="ldapTestIdentifier" placeholder="andy.cancho" /></label>
            <label class="security-field"><span class="security-field__label">Test password</span><flux:input type="password" wire:model.live="ldapTestPassword" placeholder="Contrasena del usuario LDAP" /></label>
        </div>

        <div class="security-settings-footer">
            <flux:button variant="outline" wire:click="testLdap" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="testLdap">Test bind y busqueda</span>
                <span wire:loading wire:target="testLdap">Probando LDAP...</span>
            </flux:button>
        </div>
    </section>

    <div class="security-settings-footer">
        <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">Save settings</span>
            <span wire:loading wire:target="save">Saving...</span>
        </flux:button>
    </div>
</div>

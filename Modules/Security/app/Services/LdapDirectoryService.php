<?php

namespace Modules\Security\Services;

use App\Models\User;
use App\Services\OrganizationContextService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Security\Models\SecurityRole;
use Modules\Security\Models\SecurityUserIdentity;
use RuntimeException;

class LdapDirectoryService
{
    public function __construct(private readonly OrganizationContextService $organizationContext)
    {
    }

    public function authenticate(string $login, string $password, array $settings): ?User
    {
        $this->guardPrerequisites($settings, $password);

        [$host, $port] = $this->parseHostAndPort((string) ($settings['ldap_host'] ?? ''), (int) ($settings['ldap_port'] ?? 389));
        $connection = $this->initializeNativeConnection($host, $port, $settings);

        try {
            $this->bindForSearch($connection, $settings);

            [$entry, $dn] = $this->findNativeUser($connection, $login, $settings);

            if ($entry === null || $dn === '') {
                return null;
            }

            $userConnection = $this->initializeNativeConnection($host, $port, $settings);

            try {
                if (! @ldap_bind($userConnection, $dn, $password)) {
                    throw new RuntimeException('Las credenciales LDAP son invalidas.');
                }
            } finally {
                @ldap_unbind($userConnection);
            }

            $groups = $this->resolveNativeGroups($connection, $dn, $settings);
            $mappedRoleCodes = $this->resolveMappedRoleCodes($groups, $settings);
            $isAdmin = $this->resolveAdminEligibility($groups, $settings) || collect($mappedRoleCodes)->reject(fn (string $code) => $code === 'customer')->isNotEmpty();

            return DB::transaction(function () use ($entry, $dn, $settings, $isAdmin, $login, $mappedRoleCodes, $groups): User {
                return $this->resolveLocalUserFromNative($entry, $dn, $settings, $isAdmin, $login, $mappedRoleCodes, $groups);
            });
        } finally {
            @ldap_unbind($connection);
        }
    }

    public function testConnection(array $settings, string $login = '', string $password = ''): array
    {
        $this->ensureLdapExtensionAvailable();

        [$host, $port] = $this->parseHostAndPort((string) ($settings['ldap_host'] ?? ''), (int) ($settings['ldap_port'] ?? 389));

        if ($host === '') {
            throw new RuntimeException('Falta configurar el host LDAP.');
        }

        if (trim((string) ($settings['ldap_base_dn'] ?? '')) === '') {
            throw new RuntimeException('Falta configurar el Base DN para probar busqueda LDAP.');
        }

        $connection = $this->initializeNativeConnection($host, $port, $settings);

        try {
            $this->bindForSearch($connection, $settings);

            if ($login === '') {
                return ['message' => "Conexion LDAP exitosa con bind de servicio en {$host}:{$port}."];
            }

            [$entry, $dn] = $this->findNativeUser($connection, $login, $settings);

            if ($entry === null || $dn === '') {
                throw new RuntimeException('Bind correcto, pero no se encontro el usuario con el filtro configurado.');
            }

            if ($password !== '') {
                $userConnection = $this->initializeNativeConnection($host, $port, $settings);

                try {
                    if (! @ldap_bind($userConnection, $dn, $password)) {
                        throw new RuntimeException('Se encontro el usuario, pero el bind con sus credenciales fallo: '.ldap_error($userConnection));
                    }
                } finally {
                    @ldap_unbind($userConnection);
                }
            }

            $emailAttribute = trim((string) ($settings['ldap_email_attribute'] ?? 'mail')) ?: 'mail';
            $mailValue = $this->extractNativeAttribute($entry, $emailAttribute);

            return [
                'message' => 'Conexion LDAP correcta. Usuario encontrado'.($password !== '' ? ' y credenciales validadas' : '').': '.$dn.($mailValue !== '' ? ' | '.$emailAttribute.': '.$mailValue : ''),
            ];
        } finally {
            @ldap_unbind($connection);
        }
    }

    private function guardPrerequisites(array $settings, string $password): void
    {
        $this->ensureLdapExtensionAvailable();

        if (($settings['ldap_enabled'] ?? false) !== true) {
            throw new RuntimeException('La autenticacion LDAP no esta habilitada en la configuracion de seguridad.');
        }

        if (trim((string) ($settings['ldap_host'] ?? '')) === '') {
            throw new RuntimeException('Falta configurar el host LDAP.');
        }

        if ($password === '') {
            throw new RuntimeException('La contrasena LDAP es obligatoria.');
        }
    }

    private function ensureLdapExtensionAvailable(): void
    {
        if (! extension_loaded('ldap')) {
            throw new RuntimeException('La extension PHP LDAP no esta disponible en este servidor.');
        }
    }

    private function resolveAdminEligibility(array $groups, array $settings): bool
    {
        if (empty($settings['ldap_assign_admin_by_group'])) {
            return false;
        }

        $expected = collect(explode(',', (string) ($settings['ldap_admin_group_names'] ?? '')))
            ->map(fn (string $group): string => Str::lower(trim($group)))
            ->filter()
            ->values();

        return $expected->isNotEmpty() && $expected->intersect($groups)->isNotEmpty();
    }

    private function resolveMappedRoleCodes(array $groups, array $settings): array
    {
        $mapping = trim((string) ($settings['ldap_group_role_map'] ?? ''));

        if ($mapping === '') {
            return [];
        }

        $groupSet = collect($groups)->map(fn (string $group) => Str::lower(trim($group)))->filter();
        $roleCodes = collect();

        foreach (preg_split('/\r\n|\r|\n/', $mapping) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || ! str_contains($line, '=')) {
                continue;
            }

            [$groupName, $roles] = array_map('trim', explode('=', $line, 2));

            if ($groupName === '' || ! $groupSet->contains(Str::lower($groupName))) {
                continue;
            }

            $roleCodes = $roleCodes->merge(
                collect(explode(',', (string) $roles))
                    ->map(fn (string $code) => trim($code))
                    ->filter()
            );
        }

        return $roleCodes->unique()->values()->all();
    }

    private function resolveLocalUserFromNative(array $entry, string $dn, array $settings, bool $isAdmin, string $login, array $mappedRoleCodes, array $groups): User
    {
        $identifier = $login;
        $organizationId = $this->organizationContext->currentOrganizationId();
        $email = $this->resolveEmailFromNative($entry, $settings, $identifier);
        $emailAttribute = trim((string) ($settings['ldap_email_attribute'] ?? 'mail')) ?: 'mail';
        $identity = SecurityUserIdentity::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->where('provider_type', 'ldap')
            ->where('provider_identifier', $identifier)
            ->first();

        $user = $identity?->user;

        if (! $user && $email !== '') {
            $user = User::query()
                ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
                ->where('email', $email)
                ->first();
        }

        if (! $user && empty($settings['auto_user_provisioning'])) {
            throw new RuntimeException('El usuario LDAP es valido, pero no existe un usuario local vinculado y la provision automatica esta deshabilitada.');
        }

        $displayName = $this->resolveDisplayNameFromNative($entry, $identifier);
        $phone = $this->extractNativeAttribute($entry, 'telephonenumber');

        if (! $user) {
            $user = User::query()->create([
                'name' => $displayName,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'organization_id' => $organizationId,
                'is_active' => true,
                'role' => $isAdmin ? 'super_admin' : 'customer',
                'guid' => $this->resolveGuidFromNative($entry),
                'domain' => 'ldap',
                'password' => Str::password(32),
            ]);
        } else {
            $updates = [
                'name' => $displayName,
                'phone' => $phone !== '' ? $phone : $user->phone,
                'organization_id' => $user->organization_id ?: $organizationId,
                'is_active' => true,
                'domain' => 'ldap',
            ];

            if (($user->email === '' || $user->email === null) && $email !== '') {
                $updates['email'] = $email;
            }

            $guid = $this->resolveGuidFromNative($entry);

            if ($guid !== '') {
                $updates['guid'] = $guid;
            }

            if ($isAdmin && $user->role !== 'super_admin') {
                $updates['role'] = 'super_admin';
            }

            $user->fill($updates);
            $user->save();
        }

        SecurityUserIdentity::query()->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'provider_type' => 'ldap',
                'provider_identifier' => $identifier,
            ],
            [
                'user_id' => $user->id,
                'provider_key' => 'ldap',
                'provider_email' => $this->extractNativeAttribute($entry, $emailAttribute) ?: null,
                'provider_dn' => $dn,
                'provider_payload' => $this->normalizeNativeEntryPayload($entry),
                'last_synced_at' => Carbon::now(),
            ]
        );

        $effectiveRoles = $this->syncLocalRoles($user, $mappedRoleCodes, $isAdmin);

        app(SecurityAuditService::class)->log(
            eventType: 'authentication',
            eventCode: 'security.ldap.roles.synced',
            result: 'success',
            message: 'Sincronizacion LDAP completada para el usuario autenticado.',
            actor: $user,
            target: $user,
            module: 'security',
            context: [
                'ldap_groups' => $groups,
                'assigned_roles' => $effectiveRoles,
            ],
        );

        return $user->refresh();
    }

    private function syncLocalRoles(User $user, array $mappedRoleCodes, bool $isAdmin): array
    {
        $effectiveRoleCodes = collect($mappedRoleCodes)
            ->map(fn (string $code) => trim($code))
            ->filter()
            ->unique();

        if ($isAdmin && $effectiveRoleCodes->isEmpty()) {
            $effectiveRoleCodes = $effectiveRoleCodes->push('super_admin');
        }

        if (! $isAdmin && $effectiveRoleCodes->isEmpty()) {
            $effectiveRoleCodes = $effectiveRoleCodes->push('customer');
        }

        $roles = SecurityRole::query()->whereIn('code', $effectiveRoleCodes->all())->get();

        if ($roles->isEmpty()) {
            return [];
        }

        $user->roles()->syncWithoutDetaching(
            $roles->mapWithKeys(fn (SecurityRole $role) => [
                $role->id => [
                    'scope' => 'all',
                    'is_active' => true,
                    'context' => ['source' => 'ldap'],
                ],
            ])->all()
        );

        return $roles->pluck('code')->values()->all();
    }

    private function resolveEmailFromNative(array $entry, array $settings, string $login): string
    {
        $emailAttribute = trim((string) ($settings['ldap_email_attribute'] ?? 'mail')) ?: 'mail';
        $email = $this->extractNativeAttribute($entry, $emailAttribute);

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Str::lower($email);
        }

        $domain = trim((string) ($settings['ldap_fallback_email_domain'] ?? 'ldap.local'));

        if ($domain === '') {
            [$host] = $this->parseHostAndPort((string) ($settings['ldap_host'] ?? 'ldap.local'), 389);
            $domain = filter_var($host, FILTER_VALIDATE_IP) ? 'ldap.local' : preg_replace('/[^A-Za-z0-9.-]/', '', $host);
        }

        return Str::lower(Str::slug($login, '.').'@'.$domain);
    }

    private function resolveDisplayNameFromNative(array $entry, string $fallback): string
    {
        return $this->extractNativeAttribute($entry, 'displayname')
            ?: $this->extractNativeAttribute($entry, 'cn')
            ?: trim($this->extractNativeAttribute($entry, 'givenname').' '.$this->extractNativeAttribute($entry, 'sn'))
            ?: $fallback;
    }

    private function resolveGuidFromNative(array $entry): string
    {
        return $this->extractNativeAttribute($entry, 'entryuuid')
            ?: $this->extractNativeAttribute($entry, 'objectguid');
    }

    private function normalizeNativeEntryPayload(array $entry): array
    {
        $payload = [];

        foreach ($entry as $key => $value) {
            if (is_int($key) || $key === 'count') {
                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $payload[$key] = collect($value)
                ->reject(fn ($item, $index) => $index === 'count')
                ->filter(fn ($item) => is_scalar($item))
                ->map(fn ($item) => (string) $item)
                ->values()
                ->all();
        }

        return $payload;
    }

    private function buildSearchFilter(string $template, string $usernameAttribute, string $login): string
    {
        $usernameAttribute = trim($usernameAttribute) !== '' ? trim($usernameAttribute) : 'uid';
        $escapedLogin = ldap_escape($login, '', LDAP_ESCAPE_FILTER);
        $template = trim($template);

        if ($template === '') {
            return sprintf('(%s=%s)', $usernameAttribute, $escapedLogin);
        }

        if (str_contains($template, '%s')) {
            return str_replace('%s', $escapedLogin, $template);
        }

        return '(&'.$this->wrapFilter($template).sprintf('(%s=%s)', $usernameAttribute, $escapedLogin).')';
    }

    private function wrapFilter(string $filter): string
    {
        $filter = trim($filter);

        if ($filter === '') {
            return '(objectClass=*)';
        }

        return str_starts_with($filter, '(') ? $filter : '('.$filter.')';
    }

    private function parseHostAndPort(string $host, int $fallbackPort): array
    {
        $host = trim($host);

        if ($host === '') {
            return ['', $fallbackPort];
        }

        if (! str_contains($host, ':')) {
            return [$host, $fallbackPort];
        }

        [$normalizedHost, $port] = array_pad(explode(':', $host, 2), 2, null);

        return [trim((string) $normalizedHost), is_numeric($port) ? (int) $port : $fallbackPort];
    }

    private function initializeNativeConnection(string $host, int $port, array $settings)
    {
        $connection = @ldap_connect($host, $port);

        if ($connection === false) {
            throw new RuntimeException("No se pudo abrir la conexion LDAP contra {$host}:{$port}.");
        }

        @ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);

        if (defined('LDAP_OPT_REFERRALS')) {
            @ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        }

        if (! ((bool) ($settings['ldap_verify_certificate'] ?? true)) && defined('LDAP_OPT_X_TLS_REQUIRE_CERT') && defined('LDAP_OPT_X_TLS_NEVER')) {
            @ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
        }

        if (! empty($settings['ldap_use_starttls']) && ! @ldap_start_tls($connection)) {
            throw new RuntimeException('No se pudo negociar StartTLS con el servidor LDAP: '.ldap_error($connection));
        }

        return $connection;
    }

    private function bindForSearch($connection, array $settings): void
    {
        $anonymous = (bool) ($settings['ldap_anonymous'] ?? false);

        if ($anonymous) {
            if (! @ldap_bind($connection)) {
                throw new RuntimeException('El bind anonimo fallo: '.ldap_error($connection));
            }

            return;
        }

        $bindDn = trim((string) ($settings['ldap_bind_dn'] ?? ''));
        $bindPassword = (string) ($settings['ldap_bind_password'] ?? '');

        if ($bindDn === '') {
            throw new RuntimeException('Falta configurar el Reader DN para hacer bind LDAP.');
        }

        if ($bindPassword === '') {
            throw new RuntimeException('Falta configurar la contrasena del Reader DN para hacer bind LDAP.');
        }

        if (! @ldap_bind($connection, $bindDn, $bindPassword)) {
            throw new RuntimeException('El bind del Reader DN fallo: '.ldap_error($connection));
        }
    }

    private function findNativeUser($connection, string $login, array $settings): array
    {
        $usernameAttribute = trim((string) ($settings['ldap_username_attribute'] ?? 'uid')) ?: 'uid';
        $emailAttribute = trim((string) ($settings['ldap_email_attribute'] ?? 'mail')) ?: 'mail';
        $baseDn = trim((string) ($settings['ldap_base_dn'] ?? ''));
        $filter = $this->buildSearchFilter((string) ($settings['ldap_user_filter'] ?? ''), $usernameAttribute, $login);
        $attributes = array_values(array_unique([$usernameAttribute, $emailAttribute, 'cn', 'displayname', 'givenname', 'sn', 'telephonenumber', 'entryuuid', 'objectguid']));

        $search = @ldap_search($connection, $baseDn, $filter, $attributes, 0, 1);

        if ($search === false) {
            throw new RuntimeException('La busqueda LDAP fallo: '.ldap_error($connection));
        }

        $entries = ldap_get_entries($connection, $search);

        if (($entries['count'] ?? 0) < 1) {
            return [null, ''];
        }

        $entry = $entries[0];
        $dn = (string) ($entry['dn'] ?? '');

        return [$entry, $dn];
    }

    private function resolveNativeGroups($connection, string $userDn, array $settings): array
    {
        $groupBaseDn = trim((string) ($settings['ldap_group_base_dn'] ?? ''));
        $membershipAttribute = trim((string) ($settings['ldap_group_membership_attribute'] ?? 'member')) ?: 'member';
        $groupFilter = trim((string) ($settings['ldap_group_filter'] ?? ''));

        if ($groupBaseDn === '') {
            return [];
        }

        $groupFilter = $groupFilter !== ''
            ? '(&'.$this->wrapFilter($groupFilter).'('.$membershipAttribute.'='.ldap_escape($userDn, '', LDAP_ESCAPE_FILTER).'))'
            : '('.$membershipAttribute.'='.ldap_escape($userDn, '', LDAP_ESCAPE_FILTER).')';

        $search = @ldap_search($connection, $groupBaseDn, $groupFilter, ['cn']);

        if ($search === false) {
            return [];
        }

        $entries = ldap_get_entries($connection, $search);
        $groups = [];

        for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
            $cn = $this->extractNativeAttribute($entries[$i], 'cn');

            if ($cn !== '') {
                $groups[] = Str::lower($cn);
            }
        }

        return array_values(array_unique($groups));
    }

    private function extractNativeAttribute(array $entry, string $attribute): string
    {
        $attribute = strtolower($attribute);

        if (! isset($entry[$attribute][0])) {
            return '';
        }

        return trim((string) $entry[$attribute][0]);
    }
}

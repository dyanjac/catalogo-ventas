# Modulo de Seguridad - Blueprint Inicial

## Objetivo

Definir una base de autenticacion y control de acceso extensible para el panel administrativo y futuros canales del sistema.

La implementacion se plantea alineada a buenas practicas relacionadas con controles tecnologicos de ISO 27001, sin afirmar cumplimiento o certificacion automatica. El objetivo es que la estructura de datos y el flujo de autenticacion faciliten:

- segregacion de funciones
- trazabilidad de accesos
- autenticacion federada
- integracion con directorios corporativos
- endurecimiento progresivo del acceso administrativo

## Estado actual

Hoy la autenticacion usa:

- tabla `users`
- campo plano `role`
- login local por correo y contraseña
- logout clasico por sesion

Esto sirve para ecommerce basico, pero no para un modulo de seguridad empresarial.

## Lineas de evolucion

### 1. Autenticacion

- login web dedicado
- login local con politicas de acceso
- bloqueo progresivo o throttling
- opcion de MFA en fases posteriores
- soporte para proveedores externos
- soporte para LDAP configurable

### 2. Autorizacion

- reemplazar `users.role` como unico criterio
- modelo de roles y permisos desacoplado
- asignacion por usuario
- opcion de asignacion por grupo o unidad organizativa en LDAP

### 3. Auditoria

- registrar inicios de sesion exitosos y fallidos
- proveedor usado
- IP, user agent, canal y timestamp
- eventos administrativos sensibles

## Tablas propuestas

### `security_roles`

- `id`
- `code`
- `name`
- `description`
- `is_system`
- `is_active`
- timestamps

### `security_permissions`

- `id`
- `code`
- `name`
- `module`
- `description`
- `is_system`
- timestamps

### `security_role_permission`

- `role_id`
- `permission_id`
- timestamps

### `security_user_role`

- `user_id`
- `role_id`
- `assigned_by`
- `assigned_reason`
- `starts_at`
- `ends_at`
- timestamps

### `security_auth_providers`

Proveedor configurable de autenticacion.

- `id`
- `code`
- `name`
- `type` (`local`, `ldap`, `oauth`)
- `driver` (`database`, `ldap`, `google`, `github`)
- `is_enabled`
- `is_default`
- `priority`
- `settings_json`
- timestamps

Ejemplos:

- local
- ldap_corporativo
- google_workspace
- github_enterprise

### `security_user_identities`

Relaciona usuarios internos con identidades externas.

- `id`
- `user_id`
- `provider_id`
- `external_subject`
- `external_username`
- `external_email`
- `metadata_json`
- `last_authenticated_at`
- timestamps

### `security_login_attempts`

- `id`
- `provider_id`
- `user_id` nullable
- `login_identifier`
- `ip_address`
- `user_agent`
- `channel`
- `status` (`success`, `failed`, `blocked`)
- `failure_reason`
- `attempted_at`

### `security_audit_events`

- `id`
- `user_id` nullable
- `provider_id` nullable
- `event_type`
- `module`
- `severity`
- `ip_address`
- `user_agent`
- `context_json`
- `created_at`

## Integracion LDAP configurable

Requisitos funcionales:

- multiples servidores LDAP si fuera necesario
- host, puerto, base DN, bind DN y TLS configurables
- mapeo configurable de atributos LDAP a usuario local
- reglas de provisionamiento:
  - autocreacion controlada
  - vinculacion a usuario existente
  - asignacion inicial de roles
- opcion de sincronizacion de grupos LDAP a roles locales

## Autenticacion con Google y GitHub

Se recomienda modelarla sobre `security_auth_providers` y `security_user_identities`.

Campos operativos por proveedor:

- client id
- client secret
- redirect uri
- scopes
- restricciones de dominio o tenant
- politicas de autocreacion

## Fases sugeridas

### Fase 1

- login GET dedicado
- endurecer login local
- tabla de auditoria de intentos

### Fase 2

- roles y permisos desacoplados
- migracion progresiva de `users.role`

### Fase 3

- LDAP configurable
- vinculacion de identidades

### Fase 4

- Google y GitHub OAuth
- reglas de acceso y aprovisionamiento

## Decision tecnica

No conviene meter todo de golpe en `users`. Lo correcto es mantener:

- `users` como entidad principal de identidad interna
- un subsistema de seguridad para roles, permisos, proveedores e identidades

Eso mantiene el monolito ordenado y encaja bien con la arquitectura modular del proyecto.

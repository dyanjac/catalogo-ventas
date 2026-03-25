# Plan de Migracion a SaaS Multi-Organizacion

## Objetivo

Convertir la plataforma actual en una solucion SaaS con aislamiento por organizacion y soporte explicito de entorno operativo:

- `organization_id` o `tenant_id` en datos funcionales y de configuracion
- `environment` a nivel de organizacion con valores `production` y `demo`
- capacidad de operar multiples organizaciones en la misma base de datos sin mezcla de datos

## Estado Actual Detectado

La base ya tiene una primera dimension organizacional parcial:

- existe `security_branches`
- existen relaciones `branch_id` en `users`, `orders`, `billing_documents`
- existe `SecurityBranchContextService`
- existe `SecurityScopeService` para alcance por sucursal

Tambien existe un uso aislado de `environment` en facturacion electronica:

- `billing_settings.environment`
- `billing_document_response_histories.environment`

Esto significa que no conviene rehacer el sistema. Lo correcto es introducir una capa superior de organizacion y hacer que sucursal dependa de esa organizacion.

## Decision Arquitectonica Recomendada

### Modelo recomendado

1. `organization`
2. `organization_environment`
3. `branch`
4. usuario y datos operativos

Regla:

- una organizacion tiene muchas sucursales
- una organizacion opera en un entorno principal `production` o `demo`
- los datos de negocio se filtran primero por organizacion
- el alcance por sucursal sigue existiendo dentro de una organizacion

### Convencion recomendada

Usar `organization_id` como nombre canonico en toda la plataforma.

No mezclar:

- unas tablas con `company_id`
- otras con `tenant_id`
- otras con `organization_id`

Si hoy existe `company_id` en plantillas, debe migrarse o normalizarse hacia `organization_id`.

## Cambios de Datos

### Nuevas tablas

#### `organizations`

Campos sugeridos:

- `id`
- `code` unico
- `name`
- `slug` unico
- `tax_id` nullable
- `status` enum: `active`, `suspended`, `trial`, `archived`
- `environment` enum: `production`, `demo`
- `is_demo` boolean opcional si quieren reportes rapidos
- `owner_user_id` nullable
- `settings_json` nullable
- timestamps

#### `organization_domains`

Para login y resolucion de tenant por dominio o subdominio:

- `id`
- `organization_id`
- `domain`
- `is_primary`
- `is_active`
- timestamps

#### `organization_users`

Si un mismo usuario podra pertenecer a mas de una organizacion, se recomienda pivote:

- `id`
- `organization_id`
- `user_id`
- `role` o metadatos
- `is_active`
- timestamps

Si cada usuario solo pertenecera a una organizacion, inicialmente puede mantenerse `organization_id` directo en `users`.

### Tablas existentes a extender

Minimo obligatorio:

- `users`
- `security_branches`
- `orders`
- `order_items` si hay consultas directas o reporting por tenant
- `products`
- `categories`
- `product_images`
- `unit_measures`
- `commerce_settings`
- `billing_settings`
- `billing_documents`
- `billing_document_files`
- `billing_document_response_histories`
- `document_templates`
- `security_roles`
- `security_permissions` solo si seran configurables por tenant
- `security_user_roles`
- `security_auth_settings`
- `security_audit_logs`
- `inventory` y `accounting` completos

### Matriz inicial de tablas a modificar para agregar `organization_id`

#### Core

- `users`
- `sessions` si se desea invalidacion o trazabilidad por tenant
- `jobs` solo si payload o procesamiento necesita resolver tenant en runtime

#### Commerce

- `commerce_settings`

#### Catalog

- `products`
- `categories`
- `product_images`
- `unit_measures`
- `product_branch_stocks`
- `inventory_warehouses`
- `product_warehouse_stocks`
- `inventory_movements`
- `inventory_documents`
- `inventory_document_lines`
- `inventory_transfers`
- `inventory_transfer_lines`

#### Orders / Sales

- `orders`
- `order_items`

#### Billing / ElectronicDocuments

- `billing_settings`
- `billing_documents`
- `billing_document_files`
- `billing_document_response_histories`
- `billing_sunat_operation_types` si son configurables por tenant
- `document_templates`

#### Accounting

- `accounting_settings`
- `accounting_accounts`
- `accounting_periods`
- `accounting_cost_centers`
- `accounting_entries`
- `accounting_entry_lines`
- `accounting_entry_attachments`
- `accounting_audit_logs`

#### Security

- `security_branches`
- `security_auth_settings`
- `security_audit_logs`
- `security_user_identities`
- `security_user_roles`
- `security_roles`
- `security_role_module_access`
- `security_role_permissions`

### Tablas que podrian quedar globales

Solo deben quedar globales si la regla de negocio realmente lo justifica:

- `migrations`
- `cache`
- `cache_locks`
- `failed_jobs`
- `job_batches`
- `password_reset_tokens`

Evaluar si estas tablas de catalogo de sistema deben ser globales o por tenant:

- `security_modules`
- `security_permissions`

### Patron de migracion por tabla

Para cada tabla funcional, la secuencia recomendada es:

1. agregar `organization_id` nullable
2. poblar `organization_id` con la organizacion default actual
3. crear indice por `organization_id`
4. agregar llaves foraneas y unicos compuestos con `organization_id` donde aplique
5. volver la columna obligatoria

Ejemplo de criterio:

- hoy `security_branches.code` es unico global
- en SaaS debe pasar a `unique(['organization_id', 'code'])`

Otro ejemplo:

- `inventory_warehouses.code` ya debe ser unico por sucursal
- conviene reforzar coherencia usando tambien la organizacion en consultas y validaciones

### Regla practica

Toda tabla que cumpla una de estas condiciones debe llevar `organization_id`:

- contiene datos de negocio
- contiene configuracion visible para cliente
- contiene seguridad o auditoria por cliente
- puede ser consultada en listados sin pasar por una relacion previa segura

## Jerarquia de Aislamiento

Orden recomendado de resolucion de contexto:

1. `organization_id`
2. `branch_id`
3. `user_id`

El `branch_id` nunca debe existir sin organizacion.

Regla de integridad:

- `security_branches.organization_id` obligatorio
- `users.organization_id` obligatorio
- `users.branch_id` solo valido si la sucursal pertenece a la misma organizacion
- `orders.organization_id` obligatorio
- `orders.branch_id` solo valido dentro de la misma organizacion

## Estrategia de Entornos `PRODUCCION` y `DEMO`

### Recomendacion

El entorno debe vivir en `organizations.environment` y no solo en facturacion.

Uso recomendado:

- `production`: datos reales, numeracion real, integraciones reales, documentos fiscales reales
- `demo`: datos de demostracion, restricciones de emision real, branding demo, seeds demo, reseteo opcional

### Reglas funcionales para `demo`

- bloquear emision fiscal real
- bloquear envios reales a SUNAT o proveedores externos
- marcar visualmente el tenant como demo
- aislar colas, notificaciones y webhooks reales
- permitir data de muestra y reseteo automatizado

### Marca visual obligatoria en panel admin

El panel administrativo debe mostrar una marca persistente cuando la organizacion actual tenga:

- `organizations.environment = 'demo'`

Implementacion recomendada:

- badge o banner fijo visible en header del admin
- texto explicito: `ENTORNO DEMO`
- color claramente diferenciado del entorno productivo
- visible para cualquier usuario autenticado dentro de esa organizacion
- opcional: incluir nombre de organizacion y sucursal activa

Comportamiento sugerido:

- en `production` no mostrar banner o mostrar etiqueta neutra solo en perfiles tecnicos
- en `demo` mostrar siempre el indicador para evitar operar creyendo que es productivo

### Capa tecnica para la marca demo

Crear una fuente unica de verdad, por ejemplo:

- `OrganizationContextService::currentEnvironment()`

Luego compartirlo a vistas admin mediante:

- view composer
- service provider
- componente Blade reutilizable
- layout admin comun

No se recomienda duplicar esta logica en cada vista Livewire o Blade.

## Estrategia de Migracion

### Fase 0 - Descubrimiento y catalogo

- inventariar todas las tablas del sistema por modulo
- clasificar tablas por:
  - maestras por tenant
  - transaccionales por tenant
  - configuracion por tenant
  - globales compartidas
- definir si `users` sera global o por organizacion
- definir estrategia de tenancy por dominio, subdominio o selector post-login

### Fase 1 - Base de tenancy

- crear tabla `organizations`
- agregar `organization_id` nullable a las tablas criticas
- poblar una organizacion default para la data actual
- asociar toda la data existente a la organizacion default
- volver `organization_id` obligatorio una vez completado backfill
- agregar indices compuestos por tenant

Entrega de esta fase:

- sistema sigue operando como hoy pero ya con aislamiento logico base

### Fase 2 - Sucursales dependientes de organizacion

- agregar `organization_id` a `security_branches`
- backfill usando la organizacion default
- actualizar servicios para que la sucursal actual siempre se resuelva dentro de la organizacion actual
- endurecer validaciones cruzadas `organization_id` + `branch_id`

Entrega:

- sucursales dejan de ser globales y pasan a ser internas de cada organizacion

### Fase 3 - Contexto aplicativo de organizacion

- crear `OrganizationContextService`
- resolver organizacion actual por:
  - dominio
  - subdominio
  - usuario autenticado
  - sesion
- propagar contexto a middleware, policies, queries y servicios
- exponer `organization`, `environment` y `isDemo` al layout del panel admin

Entrega:

- todo request autenticado conoce su `organization_id`
- el panel admin puede renderizar la marca `ENTORNO DEMO` de forma centralizada

### Fase 4 - Scope global por organizacion

- extender `SecurityScopeService` para aplicar primero filtro por organizacion
- revisar todos los listados administrativos y queries directos
- agregar global scopes o repositorios por modulo donde convenga
- evitar cualquier consulta sin filtro de organizacion en modulos funcionales

Entrega:

- no hay fuga de datos entre organizaciones

### Fase 5 - Configuracion multi-tenant

- migrar tablas singleton actuales para que sean por organizacion:
  - `commerce_settings`
  - `billing_settings`
  - `security_auth_settings`
  - `admin_theme_settings`
  - futuras tablas de settings
- definir `unique(organization_id)` donde corresponda
- mover branding, logo, certificados y credenciales al tenant correcto

Entrega:

- cada organizacion tiene sus propias configuraciones

### Fase 6 - Facturacion y documentos

- unificar `company_id` de `document_templates` a `organization_id`
- alinear facturacion electronica para usar `organization.environment`
- validar certificados, correlativos y endpoints por tenant
- impedir uso de credenciales productivas en `demo`

Entrega:

- facturacion segura por organizacion y por entorno

### Fase 7 - Inventario, contabilidad y auditoria

- agregar `organization_id` a tablas operativas restantes
- endurecer integridad referencial en movimientos, almacenes y asientos
- registrar auditoria con `organization_id`
- revisar jobs, eventos y colas para transportar contexto de tenant

Entrega:

- todos los modulos quedan tenant-aware

### Fase 8 - Provisionamiento SaaS

- alta automatica de organizacion nueva
- seed inicial:
  - organizacion
  - sucursal principal
  - usuario owner
  - configuracion base
  - roles base
- plantilla distinta para `demo` y `production`

Entrega:

- onboarding repetible para clientes SaaS

## Consideraciones de Codigo

### Middleware

Crear middleware de resolucion de tenant:

- `ResolveOrganizationContext`
- `EnsureOrganizationAccess`

### Servicios

Crear:

- `OrganizationContextService`
- `OrganizationProvisioningService`
- `OrganizationEnvironmentService`

Actualizar:

- `SecurityBranchContextService`
- `SecurityScopeService`
- servicios de checkout, POS, billing, inventory y accounting

### Modelos

Recomendable introducir un trait:

- `BelongsToOrganization`

Responsabilidades:

- relacion `organization()`
- helper `scopeForCurrentOrganization()`
- asignacion automatica al crear registros cuando aplique

## Reglas de Base de Datos

Indices recomendados:

- `index(['organization_id'])`
- `unique(['organization_id', 'code'])`
- `unique(['organization_id', 'slug'])`
- `index(['organization_id', 'branch_id'])`
- `index(['organization_id', 'created_at'])`

Evitar unicos globales cuando el dato debe repetirse entre clientes:

- SKU
- codigo de sucursal
- nombre de almacen
- series de documentos
- codigos contables si son configurables por cliente

## Impacto en Vistas del Panel Admin

### Layout

El cambio visual debe concentrarse primero en el layout compartido del admin, no en pantallas individuales.

Puntos a revisar en este proyecto:

- `resources/views/layouts/admin.blade.php`
- `resources/views/admin/partials/header.blade.php`
- vistas equivalentes dentro de modulos si usan layout propio

### Requerimiento funcional

Cuando el tenant actual sea `demo`, el usuario debe ver de forma inequívoca que esta operando en un entorno no productivo.

Contenido minimo sugerido:

- etiqueta `DEMO`
- mensaje `Estas operando en entorno DEMO`

Contenido opcional:

- nombre de la organizacion
- nombre de sucursal
- advertencia de que algunos procesos reales estan deshabilitados

### Criterio de aceptacion

- cualquier usuario autenticado del admin en tenant demo ve la marca
- la marca aparece en todas las pantallas del admin
- la marca desaparece automaticamente en tenant production
- no depende de la vista especifica ni del modulo

## Riesgos Principales

### Riesgo 1

Agregar `organization_id` pero no aplicarlo en todas las queries.

Resultado:

- fuga de datos entre clientes

### Riesgo 2

Mantener configuraciones singleton globales.

Resultado:

- un cliente puede pisar branding, certificados o parametros de otro

### Riesgo 3

Permitir relaciones cruzadas entre tenant y sucursal.

Resultado:

- corrupcion logica de datos

### Riesgo 4

Usar solo `APP_ENV` como sustituto de entorno de negocio.

Resultado:

- mezcla entre entorno tecnico de despliegue y entorno funcional del cliente

## Recomendaciones Especificas para este Proyecto

1. No reemplazar `branch_id`; encapsularlo debajo de `organization_id`.
2. Hacer primero multi-tenant en una sola base de datos compartida.
3. Mantener `APP_ENV` para infraestructura y `organizations.environment` para negocio.
4. Corregir pronto los casos existentes de `company_id` y normalizarlos.
5. Convertir tablas de configuracion actuales a configuracion por organizacion antes de vender como SaaS.
6. Endurecer pruebas automatizadas de aislamiento antes de abrir onboarding multiempresa.

## MVP Recomendado para salir a SaaS

Minimo para comercializar de forma razonable:

- `organizations`
- `organization_id` en todas las tablas core
- `security_branches.organization_id`
- middleware de contexto organizacional
- filtros obligatorios por organizacion en admin, ecommerce y APIs
- settings por organizacion
- `environment` por organizacion con reglas demo
- onboarding automatizado de nueva organizacion
- tests de aislamiento entre dos organizaciones

## Orden de Ejecucion Sugerido

1. diseno de modelo de datos multi-tenant
2. catalogo de tablas y matriz de impacto
3. migraciones base `organizations` + backfill
4. contexto organizacional en middleware y servicios
5. tenancy en settings y seguridad
6. tenancy en ventas, catalogo, inventario y facturacion
7. onboarding SaaS
8. hardening de pruebas, auditoria y colas

## Definiciones Pendientes

Antes de ejecutar la migracion completa deben cerrarse estas decisiones:

- un usuario puede pertenecer a varias organizaciones o solo una
- login por dominio, subdominio o selector de organizacion
- tenants `demo` se resetean automaticamente o no
- catalogo es completamente por tenant o existiran maestros globales compartidos
- contabilidad y plan de cuentas seran por tenant sin excepciones
- certificados, series y numeracion fiscal seran totalmente independientes por organizacion

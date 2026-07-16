# Contexto maestro — ERP SaaS modular

## Estado y alcance

- Proyecto: `catalogo-ventas`.
- Fecha de línea base: 2026-07-16.
- Rama y commit base: `master`, `2890d0b45c5b9f9500b495a1c12fd9cd0a7fc935`.
- Alcance documentado: FASE 00 — auditoría y planificación. No se modificó código de negocio ni esquema.

## Objetivo del sistema

Aplicación ERP SaaS para organizaciones con catálogo, ventas/POS, pedidos e-commerce, facturación electrónica peruana, inventario, seguridad y contabilidad. La separación comercial de capacidades SaaS por plan todavía no está implementada.

## Stack confirmado

- PHP 8.2+ requerido; CLI disponible: PHP 8.4.0.
- Laravel 12, Livewire 4, Vite y Bootstrap/AdminLTE.
- Base de datos relacional; motor de la instancia real: **NO CONFIRMADO**.
- `nwidart/laravel-modules` 12 para modularidad.
- Facturación electrónica: `greenter/greenter` y proveedores intercambiables.
- Colas: Laravel jobs; adaptador RabbitMQ declarado. Operación de RabbitMQ: **NO CONFIRMADA**.
- Pruebas: PHPUnit 11, SQLite en memoria configurado para pruebas.

## Arquitectura actual

Es un monolito modular Laravel. Los módulos se cargan mediante providers de `nwidart/laravel-modules`; todos los módulos declarados están habilitados en `modules_statuses.json`.

La cooperación entre módulos es principalmente directa: controladores y servicios importan modelos/servicios de otros módulos. Hay algunos contratos en `Orders`, pero no un contrato transversal de dominio ni eventos de integración persistidos.

## Módulos existentes

| Módulo | Responsabilidad observada | Estado |
|---|---|---|
| `Core` | rutas y composición base | Implementado |
| `Catalog` | productos, categorías, unidades e inventario | Implementado parcialmente |
| `Orders` | checkout e-commerce y pedidos | Implementado |
| `Sales` | POS y emisión comercial | Implementado parcialmente |
| `Billing` | comprobantes electrónicos y proveedor SUNAT | Implementado parcialmente |
| `Accounting` | cuentas, períodos y asientos | Implementado parcialmente |
| `Security` | autenticación, RBAC, sucursales y auditoría | Implementado parcialmente |
| `Commerce` | configuración comercial | Implementado |
| `ElectronicDocuments` | XML/XSLT/PDF | Implementado parcialmente |
| `Admin`, `AdminTheme` | panel y tema | Implementado |

## Tenancy confirmado

- La tabla raíz es `organizations`.
- `OrganizationContextService` resuelve la organización desde el usuario autenticado, parámetro/sesión explícita o la organización por defecto.
- El trait `BelongsToOrganization` asigna `organization_id` al crear y expone `forCurrentOrganization()`.
- El aislamiento depende del uso explícito de ese scope; no es un global scope. Las FKs no impiden por sí solas relaciones entre registros de organizaciones distintas.

## Reglas de negocio confirmadas en código

- El checkout e-commerce crea pedidos `confirmed` y descuenta stock inmediatamente.
- Los documentos internos de inventario aplican entradas o salidas únicamente al confirmarse desde estado `draft`.
- Las transferencias registran salida y entrada en la misma transacción, con estado inicial `completed`.
- La emisión electrónica puede ser síncrona o encolada; conserva historial de respuesta, XML y CDR.
- Un asiento de venta se intenta generar tras una emisión electrónica exitosa, si `auto_post_entries` lo permite.

## Convenciones encontradas

- PSR-4 por módulo: `Modules\\<Nombre>\\...`.
- Modelos de catálogo en `Modules/Catalog/app/Entities`; otros módulos usan `app/Models`.
- `organization_id` y `forCurrentOrganization()` para registros tenant-aware.
- Códigos de permisos con formato `<módulo>.<recurso>.<acción>`.
- Migraciones versionadas por fecha y cargadas desde cada módulo.

## Decisiones arquitectónicas globales para fases posteriores

1. Mantener el monolito modular; no introducir microservicios.
2. Separar entitlement comercial por organización de RBAC por usuario.
3. Mantener un núcleo de inventario operativo, aun sin módulo avanzado contratado.
4. Tratar kardex como ledger inmutable y saldos como proyección transaccional.
5. Separar documentos comercial, tributario, almacén, transporte, kardex y evento contable.
6. Registrar eventos económicos pendientes cuando Contabilidad no esté habilitada.

## Rutas y clases importantes

- `Modules/Catalog/app/Services/InventoryMovementService.php`
- `Modules/Catalog/app/Services/InventoryDocumentService.php`
- `Modules/Catalog/app/Services/InventoryTransferService.php`
- `Modules/Catalog/app/Services/ProductInventoryService.php`
- `Modules/Orders/app/Services/OrderCheckoutService.php`
- `Modules/Sales/app/Http/Controllers/SalesPosController.php`
- `Modules/Billing/app/Services/ElectronicBillingService.php`
- `Modules/Billing/app/Jobs/IssueBillingDocumentJob.php`
- `Modules/Accounting/app/Services/SalesAccountingService.php`
- `Modules/Security/app/Services/SecurityAuthorizationService.php`
- `app/Services/OrganizationContextService.php`

## Comandos relevantes

```powershell
composer install
php artisan migrate:status
php artisan test
./vendor/bin/pint --test
npm run build
```

## Restricciones y riesgos globales

- El entorno local carece de `ext-ldap`; Composer no puede instalar el lockfile normalmente.
- `vendor/autoload.php` no estaba disponible en la línea base, por lo que Artisan y PHPUnit no pudieron validarse.
- No existen planes, suscripciones ni entitlements por organización.
- No hay modelo implementado para GRE remitente/transportista ni sus flujos logísticos.
- No hay outbox ni idempotencia uniforme para operaciones críticas.

## Glosario

- **Organización / tenant:** empresa aislada lógicamente por `organization_id`.
- **RBAC:** acceso de usuario mediante roles, módulos y permisos.
- **Entitlement:** capacidad contratada por una organización; aún no existe.
- **Kardex:** registro histórico de movimientos de existencias.
- **Saldo:** proyección actual de existencias para lectura rápida.
- **Evento económico:** hecho contable pendiente o procesado, independiente del asiento.

## Preguntas abiertas

- **DECISIÓN REQUERIDA:** definir catálogo comercial de planes, addons y capacidades por organización.
- **DECISIÓN REQUERIDA:** definir política de emisión tributaria definitiva: emisión, envío o aceptación SUNAT.
- **DECISIÓN REQUERIDA:** definir si habrá lotes, series, vencimientos y custodia de terceros en el alcance inicial.
- Motor, versión y datos de producción: **NO CONFIRMADO**.

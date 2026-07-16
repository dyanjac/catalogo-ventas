# FASE 01 — CONTEXTO DE CIERRE

## 1. Identificación

- Proyecto: `catalogo-ventas`.
- Fase: 01 — Entitlements SaaS y política de módulos.
- Fecha: 2026-07-16.
- Estado: IMPLEMENTADA Y VALIDADA EN SU ALCANCE.
- Rama: `master`.
- Commit inicial: `19c007e2e40f21bdeded943d9e9b021c24972c27`.
- Commit final: SIN COMMIT; cambios de FASE 01 en el worktree.

## 2. Objetivo de la fase

Separar capacidades comerciales por organización de roles y permisos RBAC, conservando un núcleo técnico de inventario y definiendo el efecto de activar/desactivar Inventario avanzado y Contabilidad.

## 3. Alcance implementado

- Catálogo de capabilities, planes, addons y suscripciones por organización.
- Overrides comerciales por capability y organización.
- Backfill `legacy_full` para organizaciones existentes.
- Asignación de plan `basic` al provisionar organizaciones demo.
- Composición `RBAC && entitlement` para módulos comerciales mapeados.
- Middleware `tenant.capability` para rutas futuras de capacidad granular.
- Checkout e-commerce, emisión Billing y auto-post contable sujetos al entitlement correspondiente.
- Pruebas de plan básico, addon/override, aislamiento tenant y composición RBAC-entitlement.

## 4. Fuera de alcance

- Pantalla administrativa/comercial para vender o administrar planes y addons.
- Cambiar flujos de ventas, stock, kardex, comprobantes o generación de asientos.
- Diferir o re-procesar asientos cuando Contabilidad se desactive.
- Implementar reservas de stock, GRE o eventos económicos.

## 5. Decisiones tomadas

- Decisión: catálogo comercial en Commerce, no en Security. Motivo: RBAC y contratación son responsabilidades distintas. Alternativa descartada: campos/JSON en `organizations` o permisos tenant-aware. Consecuencia: tablas `saas_*` y `organization_*`.
- Decisión: `legacy_full` para organizaciones existentes y `basic` para altas nuevas. Motivo: compatibilidad segura con funcionalidades ya expuestas. Consecuencia: la migración realiza backfill y `OrganizationProvisioningService` asigna plan.
- Decisión: capacidades núcleo de inventario siempre activas. Motivo: la operación histórica no depende del addon. Consecuencia: no admiten override comercial.
- Decisión: desactivar addon bloquea acceso, no elimina datos. Motivo: trazabilidad. Consecuencia: el posteado contable existente no se altera hasta FASE 08.

## 6. Arquitectura resultante

- `Modules/Commerce` posee el catálogo y el resolver `OrganizationEntitlementService`.
- `Modules/Security` conserva roles/permisos y consulta Commerce solo al decidir acceso a módulo.
- `Organization` expone relaciones de suscripciones y overrides.
- La configuración `commerce.entitlements.module_capabilities` vincula módulos comerciales a capabilities.
- Cambios de plan, addon y override bloquean la fila de `organizations` dentro de una transacción para serializar altas y bajas de una misma organización.

## 7. Modelo de datos resultante

| Tabla | Propósito | Restricciones / rollback |
|---|---|---|
| `saas_capabilities` | Catálogo de capabilities. | `code` único; se elimina al revertir migración. |
| `saas_plans` | Planes y addons mediante `kind`. | `code` único; no usar como FK directa en organizaciones. |
| `saas_plan_capabilities` | Composición plan/addon-capability. | único `plan_id, capability_id`; cascade al catálogo. |
| `organization_plan_subscriptions` | Plan y addons asignados con estado/fechas. | FKs restrictivas a organización/plan; rollback elimina asignaciones. |
| `organization_entitlements` | Override enabled/disabled por capability. | único `organization_id, capability_id`; rollback elimina overrides. |

Migración: `Modules/Commerce/database/migrations/2026_07_16_120000_create_saas_entitlement_tables.php`.

## 8. Archivos creados

- Cuatro entidades SaaS bajo `Modules/Commerce/app/Entities/`.
- `OrganizationEntitlementService` y middleware `EnsureOrganizationCapability`.
- Migración de catálogo y suscripciones.
- `tests/Feature/OrganizationEntitlementServiceTest.php`.
- `decisiones/ADR-001-MODULOS-Y-ENTITLEMENTS.md`.

## 9. Archivos modificados

- `Modules/Commerce/config/config.php`: mapa módulo-capability.
- `Modules/Commerce/app/Providers/CommerceServiceProvider.php`: registra resolver singleton.
- `Modules/Security/app/Services/SecurityAuthorizationService.php`: compone acceso RBAC y entitlement.
- `app/Models/Organization.php`: relaciones SaaS.
- `app/Services/OrganizationProvisioningService.php`: asigna plan básico.
- `bootstrap/app.php`: alias `tenant.capability`.
- `tests/Feature/OrganizationOnboardingFlowTest.php`: verifica suscripción al provisionar.

## 10. Flujos implementados

Alta demo
→ crear organización
→ asignar plan `basic`
→ crear configuración, sucursal y administrador
→ resultado: capabilities comerciales básicas activas.

Acceso a módulo
→ validar rol/acceso RBAC
→ resolver capability comercial de la organización
→ permitir solo si ambos son verdaderos
→ resultado: acceso denegado sin entitlement aunque el usuario sea `super_admin` tenant.

Addon
→ bloquear fila de organización
→ activar suscripción addon
→ resolver capability actual sin caché persistente
→ habilitar módulo sin modificar datos operativos.

Plan u override concurrente
→ bloquear fila de organización
→ reemplazar/asignar estado dentro de una transacción
→ resultado: una operación comercial por organización se serializa antes de resolver capabilities.

Desactivación comercial
→ denegar nueva navegación y checkout/electronic billing/auto-post según capability
→ conservar comprobantes, movimientos y asientos existentes
→ resultado: no se elimina historia ni se procesa retroactivamente.

## 11. Validaciones realizadas

- `php -l` sobre entidades, servicio, middleware, migración, cambios de aplicación y prueba: exitoso.
- `git diff --check`: exitoso.
- `OrganizationEntitlementServiceTest`: 4 pruebas exitosas.
- `OrganizationOnboardingFlowTest`: 2 pruebas exitosas.
- Validación combinada FASE 01/02: 20 pruebas y 83 aserciones exitosas.
- Las migraciones completas se ejecutan en SQLite desde cero después de convertir tres backfills históricos a Query Builder portable.

## 12. Errores encontrados

- Resuelto: `vendor/autoload.php` ausente. Composer se ejecutó dentro del contenedor `erp-app`, cuyo PHP sí dispone de `ext-ldap`, y `vendor/` quedó disponible en el workspace.
- Resuelto: tres migraciones históricas usaban `UPDATE JOIN` específico de MySQL e impedían `RefreshDatabase` en SQLite. Los backfills se reescribieron con Query Builder y procesamiento por lotes.
- Resuelto: dos fixtures de pruebas enviaban arrays sin serializar al campo JSON `security_user_roles.context`.

## 13. Riesgos pendientes

- No existe interfaz administrativa para modificar suscripciones/overrides. Impacto: operación manual por servicio/DB no debe hacerse sin una capa administrativa validada. Mitigación: incorporar UI/API explícita dentro de una fase comercial posterior o ampliación aprobada.
- FASE 01 no difiere ni re-procesa eventos históricos cuando Contabilidad pierde entitlement. Impacto: los asientos existentes permanecen intactos. Mitigación: FASE 08 y FASE 10 con eventos económicos.
- Migración `down()` elimina datos SaaS introducidos. Mitigación: usar rollback solo antes de administración comercial real o exportar asignaciones.

## 14. Deuda técnica pendiente

- La resolución de capabilities no usa caché persistente para respetar expiraciones y bajas en workers/Octane; evaluar caché distribuida con invalidación y TTL en una fase de observabilidad.
- No hay historial de cambios de override; solo estado actual por organización/capability.
- No hay validación de UI/HTTP para contratos comerciales.

## 15. Compatibilidad y migración

- Las organizaciones presentes al aplicar migración reciben `legacy_full`; no pierden módulos actuales.
- Las nuevas organizaciones reciben `basic` desde provisionamiento.
- La tabla de módulos de Nwidart no cambia: todos los módulos técnicos siguen cargados globalmente.
- Rollback: las cinco tablas son reversibles por la migración; realizar backup antes de revertir en un entorno con asignaciones administradas.

## 16. Configuración requerida

- Sin nuevas variables de entorno, colas ni cron.
- El PHP CLI mínimo de Herd Lite continúa sin `ext-ldap`; para reinstalar dependencias debe usarse el PHP del contenedor `erp-app` o instalar una distribución CLI con LDAP.
- Los módulos comerciales se mapean en `Modules/Commerce/config/config.php`.

## 17. Estado actual de pruebas

- Nuevos archivos de prueba: 1; prueba de onboarding ampliada: 1.
- Resultado enfocado: 6 pruebas de FASE 01 exitosas.
- Resultado combinado con FASE 02: 20 pruebas y 83 aserciones exitosas.
- La suite global conserva fallos preexistentes de autenticación, Vite y fixtures de stock; no afectan las aserciones enfocadas de entitlements.

## 18. Criterios de aceptación

- [x] Planes, addons y capabilities separados de RBAC.
- [x] Entitlements por organización con overrides y aislamiento de tenant.
- [x] Inventario núcleo permanece fuera de desactivación comercial.
- [x] Inventario avanzado y Contabilidad bloquean acceso sin borrar datos.
- [x] Organizaciones existentes preservan acceso mediante `legacy_full`.
- [x] Nuevas organizaciones reciben plan básico.
- [x] Pruebas de cobertura añadidas y sintaxis validada.
- [x] Rutas e-commerce y procesos de Billing/Contabilidad aplican el contrato de capability antes de nuevas ejecuciones.
- [x] Pruebas PHPUnit enfocadas ejecutadas.

## 19. Próxima fase recomendada

- Código: FASE 02.
- Nombre: Normalización del producto.
- Objetivo: separar datos maestros/comerciales/tributarios de campos de inventario y contabilidad; incorporar tipo de producto y tratamiento contable heredable.
- Dependencias satisfechas: contrato de capabilities por organización.
- Riesgos iniciales: compatibilidad de `products.stock`, `average_price` y cuentas actuales.
- Archivos a revisar primero: `Modules/Catalog/app/Entities/Product.php`, `app/Http/Requests/StoreProductRequest.php`, `app/Http/Requests/UpdateProductRequest.php`, migraciones de `products`, formularios de producto y `SalesAccountingService`.

## 20. Prompt para continuar en una nueva ventana

```markdown
# CONTINUACIÓN DE IMPLEMENTACIÓN POR FASE

Trabajaremos únicamente la FASE 02 — Normalización del producto.

Lee completamente antes de cambiar código:
- docs/arquitectura/erp-modular/00_CONTEXTO_MAESTRO_PROYECTO.md
- docs/arquitectura/erp-modular/03_PLAN_IMPLEMENTACION_POR_FASES.md
- docs/arquitectura/erp-modular/fases/FASE_01_CONTEXTO_CIERRE.md
- docs/arquitectura/erp-modular/decisiones/ADR-001-MODULOS-Y-ENTITLEMENTS.md

Verifica migraciones, clases, pruebas y estado Git reales. Resume estado recibido, objetivo, dependencias, riesgos, archivos a inspeccionar y plan exacto antes de editar.

Alcance: tipos de producto, tratamiento contable HEREDAR/AUTOMATICO/MANUAL/NO_APLICA/PENDIENTE_CONFIGURACION, bases de herencia producto-categoría-empresa y compatibilidad. No implementes el ledger o reservas de inventario de FASE 03/04.

Ejecuta las validaciones disponibles y genera FASE_02_CONTEXTO_CIERRE.md. No avances a FASE 03.
```

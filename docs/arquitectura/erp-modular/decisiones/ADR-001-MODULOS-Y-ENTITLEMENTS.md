# ADR-001 — Entitlements SaaS separados de RBAC

## Estado

Aceptada en FASE 01, con validación automatizada pendiente por entorno.

## Contexto

`security_*` modela qué puede hacer un usuario, pero no qué ha contratado una organización. Mezclar ambos conceptos impediría conservar operaciones técnicas y aplicar planes/addons por tenant.

## Decisión

Se crea un catálogo comercial independiente en Commerce:

- `saas_capabilities`: capacidades técnicas y comerciales.
- `saas_plans`: planes y addons, diferenciados por `kind`.
- `saas_plan_capabilities`: composición de capacidades por plan/addon.
- `organization_plan_subscriptions`: historial de plan y addons por organización.
- `organization_entitlements`: override comercial puntual por organización/capacidad.

La autorización de módulo se compone como `RBAC && entitlement` para los módulos mapeados. `hasPermission()` permanece estrictamente RBAC. Un `super_admin` no evita el entitlement de su organización; la consola sin organización continúa disponible para operación de plataforma.

## Política inicial

- Organizaciones existentes reciben `legacy_full` mediante migración para no perder acceso.
- Nuevas organizaciones demo reciben `basic` durante provisionamiento.
- `basic` incluye ventas, POS, clientes, e-commerce y facturación electrónica.
- `inventory_advanced` y `accounting` son addons; `enterprise` los incluye.
- `inventory.core.stock` e `inventory.core.movements` son capacidades técnicas núcleo y no se desactivan comercialmente.
- Desactivar Inventario avanzado o Contabilidad bloquea navegación/rutas asociadas, conserva todos los datos y no borra asientos ni movimientos. La generación automática de nuevos asientos queda bloqueada; el procesamiento histórico se tratará con eventos económicos en FASE 08/10.

## Consecuencias

- Las tablas RBAC no adquieren `organization_id` ni datos de suscripción.
- Billing y el auto-post de ventas consultan el entitlement antes de iniciar una nueva emisión o asiento. El diferimiento/reproceso histórico requiere FASE 08.
- La activación/desactivación administrativa aún se expone por servicio de aplicación, no por una pantalla de gestión comercial.
- El middleware `tenant.capability` queda disponible para capacidades que no correspondan a un módulo completo.

## Alternativas descartadas

- Guardar plan/capabilities en `organizations.settings_json`: no permite integridad, historial ni consultas fiables.
- Añadir plan o capabilities a roles/permisos: mezcla contrato SaaS con autorización de usuario.
- Deshabilitar módulos en `modules_statuses.json` por tenant: es configuración global de código, no estado comercial tenant-aware.

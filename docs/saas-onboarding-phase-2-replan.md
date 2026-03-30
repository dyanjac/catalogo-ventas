# SaaS Onboarding Fase 2 Replan

## Objetivo

Replantear la Fase 2 del onboarding SaaS para ejecutarla en cortes pequeños y verificables.

La Fase 2 ya no se tratará como un bloque único. Se divide en subfases con alcance controlado.

## Estado actual

Ya existe:

- listado de organizaciones
- formulario de alta rápida
- creación de organización en `demo`
- creación de sucursal principal
- creación de usuario admin inicial
- creación de `commerce_settings`, `billing_settings` y `accounting_settings`
- vista detalle de organización

Todavía no existe:

- checklist visible de preparación para producción
- acción explícita para promover a `production`
- auditoría del cambio de entorno
- edición operativa previa al pase a producción

## Nuevo enfoque

### Fase 2A

Objetivo:
- mostrar el estado de preparación para producción en la vista detalle

Incluye:
- calcular checks en backend
- enviar checks a la vista
- mostrar checklist visual `OK / Pendiente`

No incluye:
- botón de activación
- cambio de entorno
- auditoría

Criterio de salida:
- una organización demo muestra claramente qué le falta para producción

### Fase 2B

Objetivo:
- habilitar la ruta y la acción backend para promoción a producción

Incluye:
- nueva ruta `activate-production`
- método de controlador
- validación server-side de checks mínimos

No incluye:
- auditoría
- mejoras de UX adicionales

Criterio de salida:
- el backend puede rechazar o aceptar el pase a producción con reglas mínimas

### Fase 2C

Objetivo:
- conectar la acción desde la UI

Incluye:
- botón `Activar en producción`
- bloqueo visual si faltan checks
- mensajes de error y éxito

No incluye:
- auditoría detallada
- formularios extra de edición

Criterio de salida:
- un super admin puede promover desde la ficha si la organización está lista

### Fase 2D

Objetivo:
- registrar auditoría y persistir metadata del cambio

Incluye:
- evento de auditoría `saas.organization.production_activated`
- marca en `settings_json` con fecha de activación

Criterio de salida:
- la promoción a producción queda trazable

## Checks mínimos de readiness

- organización con `name`, `code` y `slug`
- organización `active`
- `tax_id` informado
- al menos una sucursal activa
- sucursal principal activa
- al menos un usuario admin activo
- `commerce_settings` existentes con nombre comercial e email
- `billing_settings` existentes
- `accounting_settings` existentes

## Qué queda fuera de Fase 2

- edición completa de organización
- suspensión/reactivación
- wizard de activación productiva
- validación fiscal avanzada
- certificado digital
- series definitivas y credenciales reales

## Orden recomendado

1. ejecutar `2A`
2. validar visualmente el checklist
3. ejecutar `2B`
4. ejecutar `2C`
5. cerrar con `2D`

## Próximo paso

Retomar por `Fase 2A` solamente.

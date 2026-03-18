# Fase 7 - Sucursales Reales para Alcance `branch`

## Objetivo

Eliminar la degradacion temporal del scope `branch` y reemplazarla por una dimension organizacional persistente.

## Cambios introducidos

- tabla `security_branches`
- `branch_id` en `users`
- `branch_id` en `orders`
- `branch_id` en `billing_documents`

## Regla operativa actual

- `own`: filtra por propietario del registro
- `branch`: filtra por `branch_id` del usuario autenticado
- `all`: acceso completo

## Modulos con `branch` real

- `customers`
- `sales`
- `billing`

## Flujo de datos

- cada usuario administrativo tiene una sucursal base
- nuevos pedidos ecommerce y POS heredan `branch_id`
- nuevos comprobantes heredan `branch_id` del flujo de venta

## UI administrativa

La configuracion se gestiona desde el modulo `Security`:

- `admin/security/branches`
- `admin/security/users`

## Nota

Catalogo y otros modulos siguen sin alcance por fila basado en sucursal, porque sus entidades operativas todavia no usan `branch_id`.

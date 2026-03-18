# Fase 8 - Catalogo e Inventario por Sucursal

## Objetivo

Extender la dimension organizacional de sucursales al catalogo operativo para que el alcance `branch` no solo proteja accesos, sino tambien stock y consumo real.

## Cambios principales

- tabla `product_branch_stocks`
- stock por sucursal para cada producto
- backfill inicial desde `products.stock` hacia la sucursal default
- sync del stock agregado en `products.stock` para compatibilidad

## Enforcement RBAC

El servicio `SecurityScopeService` ahora resuelve alcance real tambien para:

- `catalog`
- `inventory`

Con esto:

- usuarios con scope `branch` en catalogo solo ven productos con stock en su sucursal
- usuarios con scope `branch` en inventario solo ven registros de stock de su sucursal

## Flujos actualizados

- administracion de productos
- carrito publico
- checkout ecommerce
- POS

Todos los consumos de stock ahora descuentan desde `product_branch_stocks` y luego sincronizan el agregado global del producto.

## Pantallas

- `admin/products`
  - muestra stock efectivo por sucursal
- `admin/inventory`
  - primer screen real del modulo Inventarios

## Compatibilidad

Se mantiene `products.stock` y `products.min_stock` como agregado para no romper pantallas o integraciones heredadas.

## Siguiente paso recomendado

La siguiente fase deberia introducir movimientos de inventario por sucursal:

- entradas
- salidas
- transferencias
- ajustes
- kardex

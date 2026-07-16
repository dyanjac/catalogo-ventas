# ADR-003 — Ledger de inventario y saldo objetivo

- Estado: aceptado e implementado en FASE 03
- Fecha: 2026-07-16

## Contexto

El sistema mantenía stock simultáneamente en `products`, `product_branch_stocks` y `product_warehouse_stocks`. `inventory_movements` funcionaba como kardex, pero permitía actualización y borrado, no tenía idempotencia ni una relación versionada con el saldo. Además, ventas y POS descuentan por sucursal sin seleccionar almacén, mientras las guías operan por almacén.

## Decisión

- `inventory_movements` se conserva como único ledger y pasa a ser append-only.
- `inventory_balances` es la proyección mutable del saldo físico por producto y ubicación.
- Una ubicación es `warehouse:{id}` o `unallocated:{branch_id}`. La segunda representa de forma explícita el stock de los flujos actuales que todavía no asignan almacén; no selecciona un almacén arbitrario ni adelanta despacho de FASE 05/06.
- Cada movimiento ledger tiene clave idempotente por organización, hash del payload, versión del saldo, fecha efectiva y generación de ledger.
- Un reverso nunca modifica el movimiento original: crea un movimiento compensatorio con `reversal_of_id` único.
- Los tipos respaldados son `opening_stock`, `inbound`, `outbound`, `adjustment` y `reversal`. Los motivos se almacenan separadamente en `reason_code` y el texto libre legacy permanece en `reason`.
- Stock inicial y ajuste formal reutilizan `inventory_documents`: `opening_stock` registra una cantidad inicial y `stock_adjustment` registra un conteo objetivo.
- Las escrituras nuevas actualizan ledger, saldo objetivo y espejos legacy dentro de la misma transacción. El espejo permite rollback operativo de lectura.
- El rollout por organización usa `off`, `shadow` y `active`. Solo `active` lee `inventory_balances`; volver a `shadow` u `off` recupera la lectura legacy.
- `active` exige una conciliación previa exitosa. La conciliación registra ejecuciones e incidencias y nunca repara silenciosamente.
- El backfill es un comando idempotente y separado de la migración. Crea baselines de generación 1 sin reescribir los movimientos históricos anteriores.

## Invariantes

1. Un cambio de saldo y su movimiento se confirman o revierten juntos.
2. `stock_after = stock_before + quantity`.
3. La versión aumenta de uno en uno por saldo.
4. Repetir la misma clave y payload devuelve el mismo movimiento sin cambiar saldo; un payload diferente se rechaza.
5. Producto, sucursal, almacén y saldo pertenecen a la misma organización.
6. Los movimientos no se actualizan ni eliminan por Eloquent ni por SQL en SQLite/MySQL.
7. Productos que no controlan inventario físico no generan movimientos.
8. `reserved_stock` e `in_transit_stock` permanecen en cero; su comportamiento pertenece a fases posteriores.

## Backfill y conciliación

Por producto y sucursal, el backfill crea:

- un saldo por cada almacén existente;
- un saldo no asignado igual a `stock sucursal - suma almacenes`, con mínimo cero;
- si no existe saldo por sucursal, un fallback desde `products.stock` únicamente en la sucursal predeterminada de la misma organización.

Las divergencias, incluido un total de almacenes mayor al saldo de sucursal, se detectan en conciliación. No se ocultan escogiendo el mayor valor ni se corrigen automáticamente.

## Despliegue y rollback operativo

1. Ejecutar migraciones; el rollout queda apagado.
2. Ejecutar `inventory:ledger-backfill --organization=ID --dry-run`.
3. Ejecutar el backfill sin `--dry-run`.
4. Ejecutar `inventory:ledger-reconcile ID`.
5. Cambiar a `shadow` y observar incidencias.
6. Ejecutar una nueva conciliación y cambiar a `active`.

Ante una incidencia, ejecutar `inventory:ledger-rollout ID shadow`. La lectura vuelve al saldo legacy y las nuevas escrituras continúan dejando trazabilidad y espejo.

## Consecuencias

- Se obtiene trazabilidad inmutable, stock inicial formal, ajustes auditables y reversos compensatorios.
- Durante la transición permanecen las columnas/tablas legacy; no deben eliminarse hasta completar fases de integración.
- Los bloqueos avanzados, reservas, expiración, política de stock negativo y pruebas reales de deadlock quedan para FASE 04.
- La asignación de almacén para ventas/POS queda visible como stock no asignado hasta las fases operativas y de integración.

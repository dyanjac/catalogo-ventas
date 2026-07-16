# FASE 03 — Contexto de cierre

## 1. Identificación

- Fase: 03
- Nombre: Núcleo de inventarios — ledger y saldo objetivo
- Fecha de cierre: 2026-07-16
- Estado: implementada y validada
- Base recibida: `a5b7b40` (`fase02`), rama `master`, worktree limpio al iniciar

## 2. Objetivo cumplido

Se convirtió el kardex existente en un ledger inmutable y versionado, se creó la proyección de saldo objetivo, se formalizaron stock inicial, ajustes y reversos, y se incorporó backfill/conciliación idempotente con rollout reversible por organización.

## 3. Alcance implementado

- `inventory_balances` por organización, producto y ubicación.
- Ubicaciones tipadas `warehouse` y `unallocated`.
- `inventory_movements` con idempotencia, hash, versión, razón tipada, reverso, generación y fecha efectiva.
- Inmutabilidad Eloquent y triggers SQLite/MySQL para update/delete.
- Tipos y motivos respaldados por enums PHP 8.2+.
- Writer transaccional con aislamiento tenant explícito y espejo legacy.
- Costo promedio ponderado en ingresos valorizados.
- Reversos compensatorios con una reversión máxima por movimiento.
- Documentos `opening_stock` y `stock_adjustment`, confirmación idempotente e inmutabilidad tras confirmar.
- UI administrativa para stock inicial y conteo/ajuste.
- Backfill por lotes y `--dry-run`.
- Conciliación durable ledger ↔ saldo ↔ sucursal/producto legacy.
- Rollout `off` / `shadow` / `active`, con activación condicionada a conciliación exitosa.
- Lecturas nuevas en `ProductInventoryService` cuando el tenant está en `active`.
- Compatibilidad temporal mediante doble escritura a `product_warehouse_stocks`, `product_branch_stocks` y `products.stock`.
- Rechazo de movimientos físicos para productos cuyo tipo no controla inventario.

## 4. Fuera de alcance respetado

- Reservas y expiración.
- Política nueva de stock negativo; se preservó la validación que ya existía.
- Estrategia avanzada de reintentos/deadlocks y pruebas de concurrencia real.
- Stock en tránsito y recepción parcial.
- Asignación de almacén para checkout/POS.
- Cambiar el momento de descuento de pedidos o comprobantes.
- Eliminar columnas o tablas legacy.

## 5. Decisiones tomadas

- Se reutilizó `inventory_movements`; crear otro ledger habría duplicado la trazabilidad.
- Los movimientos históricos conservan `ledger_generation = null`; el backfill crea baselines de generación 1 y no los reescribe.
- Los flujos sin almacén usan `unallocated:{branch_id}` para evitar inventar una asignación logística.
- La idempotencia se protege con índice único `(organization_id, idempotency_key)` y hash del payload.
- El saldo tiene clave no nula `location_key`, evitando diferencias de índices únicos con `NULL` entre motores.
- El rollout es configuración técnica independiente de entitlements comerciales.
- La conciliación detecta y registra drift, pero no repara automáticamente.

Documento de decisión: `docs/arquitectura/erp-modular/decisiones/ADR-003-LEDGER-INVENTARIO-Y-SALDO-OBJETIVO.md`.

## 6. Arquitectura resultante

```text
Documento / venta / transferencia legacy
                |
                v
      InventoryMovementService
                |
                v
       InventoryLedgerService
         |              |
         v              v
inventory_movements  inventory_balances
         \              /
          \            /
           v          v
       espejos legacy temporales
 products / branch stocks / warehouse stocks

Lectura: rollout off|shadow -> legacy
         rollout active     -> inventory_balances
```

## 7. Modelo de datos resultante

La migración `2026_07_16_150000_create_inventory_ledger_core.php`:

- crea `inventory_balances`;
- amplía `inventory_movements`;
- agrega `target_quantity` a items de documentos;
- crea `inventory_ledger_rollouts`;
- crea `inventory_reconciliation_runs` e `inventory_reconciliation_issues`;
- crea triggers de inmutabilidad en SQLite/MySQL;
- incluye `down()` y fue verificada con rollback/reaplicación.

## 8. Comandos operativos

```bash
php artisan inventory:ledger-backfill --organization=ID --dry-run
php artisan inventory:ledger-backfill --organization=ID
php artisan inventory:ledger-reconcile ID
php artisan inventory:ledger-rollout ID shadow
php artisan inventory:ledger-rollout ID active
php artisan inventory:ledger-rollout ID off
```

`active` falla si la última conciliación no terminó en `passed`.

## 9. Archivos principales

- `Modules/Catalog/database/migrations/2026_07_16_150000_create_inventory_ledger_core.php`
- `Modules/Catalog/app/Data/InventoryMovementCommand.php`
- `Modules/Catalog/app/Entities/InventoryBalance.php`
- `Modules/Catalog/app/Entities/InventoryMovement.php`
- `Modules/Catalog/app/Enums/Inventory*.php`
- `Modules/Catalog/app/Services/InventoryLedgerService.php`
- `Modules/Catalog/app/Services/InventoryMovementService.php`
- `Modules/Catalog/app/Services/InventoryLedgerBackfillService.php`
- `Modules/Catalog/app/Services/InventoryReconciliationService.php`
- `Modules/Catalog/app/Services/InventoryLedgerRolloutService.php`
- `Modules/Catalog/app/Services/InventoryBalanceReadService.php`
- `Modules/Catalog/app/Services/InventoryDocumentService.php`
- `Modules/Catalog/app/Services/ProductInventoryService.php`
- `Modules/Catalog/app/Console/*InventoryLedger*.php`
- `app/Livewire/Admin/InventoryIndex.php`
- `resources/views/livewire/admin/inventory-index.blade.php`
- `tests/Feature/InventoryLedgerTest.php`

## 10. Validaciones ejecutadas

- `php vendor/bin/pint --dirty`: 28 archivos, PASS.
- `php vendor/bin/phpunit tests/Feature/InventoryLedgerTest.php`: 12 pruebas, 38 aserciones, PASS.
- Migración completa SQLite: PASS.
- Rollback de la migración FASE 03 con `--step=1`: PASS.
- Reaplicación de la migración: PASS.
- Backfill `--dry-run` sobre esquema vacío: PASS.
- Conciliación sobre esquema vacío: `passed`.
- Activación de rollout tras conciliación: PASS.
- Compilación de plantillas con `php artisan view:cache`: PASS.
- Suite global: 53 pruebas, 46 pasan, 7 fallan; son exactamente las 7 fallas históricas registradas al cerrar FASE 02.

## 11. Cobertura enfocada

Las pruebas nuevas validan:

- movimiento + saldo + espejo legacy;
- baseline runtime y versión monotónica;
- replay idempotente y conflicto de payload;
- inmutabilidad por Eloquent y SQL;
- reverso compensatorio;
- backfill warehouse/no asignado, dry-run y segunda ejecución;
- conciliación, activación y rollback de lectura;
- stock inicial y ajuste mediante documentos idempotentes;
- aislamiento entre organizaciones;
- rechazo de stock para servicios/no inventariables.
- preservación de saldos inactivos y mínimos no duplicados;
- drift exacto de almacén aunque los agregados coincidan;
- rechazo de documentos fuera del tenant activo y ajuste objetivo cero.

## 12. Revisión independiente

La revisión sniper detectó y se corrigió antes del cierre:

- render de `movement_type` después de convertirlo en enum;
- recuperación básica ante colisión de clave idempotente o creación simultánea del saldo;
- conciliación exacta contra cada `product_warehouse_stocks` y detección de faltantes;
- preservación de estado activo/inactivo en backfill;
- eliminación del doble conteo de mínimos por almacén/no asignado;
- cierre tenant en creación y confirmación de documentos;
- persistencia correcta de un conteo objetivo igual a cero.

## 13. Fallas globales preexistentes

Permanecen sin cambio:

- expectativas antiguas de login/acceso administrativo;
- fixture de checkout con `products.stock` pero sin saldo por sucursal;
- smoke test sin `RefreshDatabase`;
- vistas que requieren `public/build/manifest.json` en dos pruebas de módulos.

No se corrigieron porque no pertenecen al alcance del ledger y la misma matriz de 7 fallas ya estaba documentada en FASE 02.

## 14. Riesgos pendientes

- La semántica real de `lockForUpdate` y deadlocks debe validarse contra MySQL/MariaDB; esta estación no tuvo un servidor MySQL accesible.
- Checkout/POS todavía operan sin almacén y alimentan `unallocated`; se resolverá al integrar reserva, despacho y canales.
- Los espejos legacy siguen existiendo y pueden ser mutados por código externo o SQL; la conciliación permite detectar esa divergencia.
- El backfill debe ejecutarse y conciliarse tenant por tenant antes de activar la lectura nueva.
- Los movimientos legacy previos no adquieren versión ni se reescriben; el baseline marca el inicio reconstruible del ledger vigente.

## 15. Rollback

Rollback operativo recomendado:

1. `php artisan inventory:ledger-rollout ID shadow` para volver inmediatamente a lectura legacy.
2. Investigar con `inventory:ledger-reconcile ID`.
3. Mantener doble escritura mientras se corrige.

Rollback de esquema:

- colocar todos los tenants en `off`;
- exportar conciliaciones necesarias;
- ejecutar el `down()` de la migración FASE 03;
- el rollback elimina únicamente artefactos nuevos y conserva los saldos legacy.

## 16. Criterios de aceptación

- [x] Tabla de saldos objetivo definida.
- [x] Movimientos inmutables y versionados.
- [x] Tipos y motivos definidos.
- [x] Stock inicial mediante movimiento formal.
- [x] Entradas y salidas integradas al writer.
- [x] Reversión compensatoria implementada.
- [x] Auditoría y referencias conservadas.
- [x] Backfill y dry-run idempotentes.
- [x] Conciliación durable sin reparación silenciosa.
- [x] Feature flag por tenant y rollback a lectura anterior.
- [x] Migración/rollback y pruebas enfocadas exitosas.
- [x] Reservas y concurrencia avanzada no implementadas.

## 17. Próxima fase recomendada

- Código: FASE 04.
- Nombre: Reservas y concurrencia.
- Objetivo: reservas, expiración, bloqueo, idempotencia de comandos de negocio, política de no-stock-negativo y manejo probado de deadlocks/reintentos.
- Dependencia satisfecha: ledger inmutable y saldo objetivo de FASE 03.
- No iniciar operaciones completas de despacho/recepción/transfers en tránsito de FASE 05.

## 18. Prompt para continuar en una nueva ventana

```markdown
# CONTINUACIÓN DE IMPLEMENTACIÓN POR FASE

Trabajaremos únicamente la FASE 04 — Reservas y concurrencia.

Lee completamente antes de cambiar código:
- docs/arquitectura/erp-modular/00_CONTEXTO_MAESTRO_PROYECTO.md
- docs/arquitectura/erp-modular/02_ARQUITECTURA_OBJETIVO.md
- docs/arquitectura/erp-modular/03_PLAN_IMPLEMENTACION_POR_FASES.md
- docs/arquitectura/erp-modular/fases/FASE_03_CONTEXTO_CIERRE.md
- docs/arquitectura/erp-modular/decisiones/ADR-003-LEDGER-INVENTARIO-Y-SALDO-OBJETIVO.md

Verifica estado Git, migraciones, comandos, pruebas y rollout reales. Resume estado recibido, objetivo, dependencias, riesgos y plan antes de editar.

Alcance: reservas, expiración, bloqueo, idempotencia de comandos, política de stock negativo, deadlocks y reintentos sobre inventory_balances. Conserva los espejos legacy y el rollout. No implementes todavía despacho/recepción completos, transferencias en tránsito ni integración de canales de FASE 05/06.

Ejecuta validaciones y genera FASE_04_CONTEXTO_CIERRE.md. No avances a FASE 05.
```

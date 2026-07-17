# FASE 04 — Contexto de cierre

## 1. Identificación

- Fase: 04
- Nombre: Reservas y concurrencia
- Fecha de cierre: 2026-07-16
- Estado: implementada y validada
- Base recibida: `42d90bf` (`fase03`), rama `master`, worktree limpio al iniciar

## 2. Objetivo cumplido

Se implementó disponibilidad basada en stock físico y reservado, reservas multi-línea atómicas, expiración, liberación idempotente, orden estable de bloqueos, reintentos transaccionales y prevención estricta de stock negativo o comprometido.

## 3. Alcance implementado

- `inventory_reservations`, `inventory_reservation_items` e `inventory_reservation_events`.
- Estados `active`, `released` y `expired`.
- Eventos `reserved`, `released` y `expired` append-only.
- Idempotencia durable mediante clave única por organización y hash canónico.
- Reserva todo-o-nada de múltiples saldos con `lockForUpdate` ordenado.
- Fórmula de lectura `disponible = físico - reservado` para rollout `active`.
- `reservation_version` separada de la versión física del ledger.
- TTL predeterminado y máximo configurables.
- Comando y scheduler de expiración.
- Comando explícito de liberación masiva para rollback operativo.
- Bloqueo de downgrade `active -> shadow/off` con reservas pendientes.
- Conciliación entre `reserved_stock` y la suma de items activos.
- Detección de reservas activas ya vencidas.
- Triggers SQLite/MySQL para invariantes e inmutabilidad.
- Protección del ledger: ninguna mutación física puede invadir stock reservado.

## 4. Fuera de alcance respetado

- Consumir una reserva y generar despacho físico.
- Despacho, recepción, recepción parcial y stock en tránsito.
- Integración de reservas con checkout, pedidos, POS o facturación.
- Asignación FEFO, lotes, series o almacén automático.
- Eliminación de espejos y columnas legacy.

## 5. Invariantes

1. `physical_stock >= reserved_stock >= 0`.
2. La disponibilidad nunca es negativa.
3. Una reserva multi-item se confirma completa o no cambia ningún saldo.
4. Los saldos se bloquean siempre por ID ascendente.
5. Repetir clave y payload no duplica reserva, evento ni cantidad reservada.
6. Una clave reutilizada con otro payload se rechaza.
7. Liberación y expiración no cambian stock físico.
8. Los estados terminales no admiten una transición nueva.
9. Producto, ubicación, reserva y saldo pertenecen a la misma organización.
10. Items y eventos no se actualizan ni eliminan.

## 6. Comandos operativos

```bash
php artisan inventory:reservations-expire --dry-run
php artisan inventory:reservations-expire --organization=ID --limit=500
php artisan inventory:reservations-release ID --all-active --dry-run
php artisan inventory:reservations-release ID --all-active
php artisan inventory:ledger-reconcile ID
php artisan inventory:ledger-rollout ID shadow
```

El scheduler ejecuta la expiración cada minuto y evita solapamiento local durante cinco minutos.

## 7. Configuración

```dotenv
INVENTORY_RESERVATION_TTL_MINUTES=30
INVENTORY_RESERVATION_MAX_TTL_MINUTES=1440
INVENTORY_RESERVATION_EXPIRE_BATCH_SIZE=500
INVENTORY_RESERVATION_TRANSACTION_ATTEMPTS=5
```

## 8. Migración y rollback

La migración `2026_07_16_160000_create_inventory_reservations.php`:

- añade `reservation_version` a `inventory_balances`;
- crea cabeceras, items y eventos;
- crea índices únicos y de operación;
- crea triggers compatibles con SQLite y MySQL/MariaDB;
- elimina triggers y tablas en orden seguro en `down()`.

Rollback operativo:

1. Contar reservas con `inventory:reservations-release ID --all-active --dry-run`.
2. Liberarlas con el mismo comando sin `--dry-run`.
3. Ejecutar `inventory:ledger-reconcile ID`.
4. Cambiar el rollout a `shadow` u `off`.
5. Solo después considerar rollback de esquema.

## 9. Archivos principales

- `Modules/Catalog/database/migrations/2026_07_16_160000_create_inventory_reservations.php`
- `Modules/Catalog/app/Data/InventoryReservationCommand.php`
- `Modules/Catalog/app/Data/InventoryReservationItemData.php`
- `Modules/Catalog/app/Entities/InventoryReservation*.php`
- `Modules/Catalog/app/Enums/InventoryReservation*.php`
- `Modules/Catalog/app/Services/InventoryReservationService.php`
- `Modules/Catalog/app/Services/InventoryLedgerService.php`
- `Modules/Catalog/app/Services/InventoryBalanceReadService.php`
- `Modules/Catalog/app/Services/InventoryReconciliationService.php`
- `Modules/Catalog/app/Services/InventoryLedgerRolloutService.php`
- `Modules/Catalog/app/Console/*InventoryReservationsCommand.php`
- `tests/Feature/InventoryReservationTest.php`

## 10. Validaciones ejecutadas

- `php vendor/bin/pint --dirty`: PASS.
- PHPUnit enfocado FASE 03 + FASE 04: 24 pruebas, 80 aserciones, PASS.
- Migración completa sobre MySQL 8.4: PASS.
- Carrera MySQL/InnoDB con dos reservas simultáneas de 6 sobre stock físico 10: una reserva confirmada y una rechazada; saldo final físico 10, reservado 6, disponible 4.
- Carrera MySQL/InnoDB con la misma clave idempotente: ambos procesos devolvieron la misma reserva; una fila, reservado 4 y no 8.
- Carrera MySQL/InnoDB al límite con la misma clave y cantidad 10 sobre stock 10: ambos procesos devolvieron la misma reserva; reservado final 10.
- Carrera MySQL/InnoDB de liberación con la misma clave: ambos procesos devolvieron estado `released`; una sola transición y reservado final 0.
- Carrera MySQL/InnoDB del ledger con salida idempotente de 10 sobre stock 10: ambos procesos devolvieron el mismo movimiento; físico final 0 y un solo movimiento.
- Rollback y reaplicación exclusiva de la migración FASE 04 en MySQL 8.4: PASS.
- Suite global: 62 pruebas, 55 pasan y 7 fallan; son exactamente las 7 fallas históricas documentadas en FASE 03.
- Revisión sniper independiente: completada; todos los hallazgos P1/P2 fueron corregidos y revalidados.

## 11. Cobertura enfocada

- reserva, replay y conflicto de payload;
- límite exacto e insuficiencia;
- atomicidad multi-saldo;
- liberación idempotente y transición terminal;
- expiración por tiempo y comando repetible;
- aislamiento tenant y requisito de rollout activo;
- salida física que intenta apropiarse de stock reservado;
- bloqueo de rollback y liberación operativa;
- drift de conciliación;
- inmutabilidad SQL de items y eventos.

## 12. Decisiones y riesgos

- Las reservas solo operan con rollout `active`; así el sistema no promete unidades usando una fuente de lectura distinta.
- Los espejos legacy conservan físico. Restar reservado en ellos duplicaría estados y haría inseguro el rollback.
- MySQL/InnoDB es la referencia para concurrencia; SQLite sirve para reglas funcionales pero no demuestra locks de fila.
- `withoutOverlapping` no es garantía de negocio; los locks, transacciones e índices únicos sí lo son.
- La integración de una reserva con el ciclo del pedido debe definir cuándo deja de expirar (por ejemplo, pago aprobado) en FASE 06.

Documento de decisión: `docs/arquitectura/erp-modular/decisiones/ADR-004-RESERVAS-Y-CONCURRENCIA-DE-INVENTARIO.md`.

## 13. Criterios de aceptación

- [x] Stock físico, reservado y disponible definidos.
- [x] Reservas atómicas multi-saldo.
- [x] Idempotencia durable.
- [x] Expiración y liberación idempotente.
- [x] Bloqueos ordenados y reintentos configurables.
- [x] Prevención de stock físico por debajo del reservado.
- [x] Conciliación y rollback operativo.
- [x] Compatibilidad con ledger y espejos de FASE 03.
- [x] Pruebas funcionales y una carrera real MySQL exitosas.
- [x] Segunda carrera MySQL y suite global completadas.
- [x] Revisión independiente completada.

## 14. Próxima fase recomendada

- Código: FASE 05.
- Nombre: Operaciones avanzadas de inventario.
- Objetivo: despacho/recepción, consumo de reservas, transferencias y stock en tránsito sin adelantar la integración completa de canales.
- Dependencia recomendada: diseñar consumo/despacho de reservas sin integrar todavía todos los canales de FASE 06.

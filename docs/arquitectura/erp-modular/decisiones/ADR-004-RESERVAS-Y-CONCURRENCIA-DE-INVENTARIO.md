# ADR-004 — Reservas y concurrencia de inventario

- Estado: aceptado e implementado en FASE 04
- Fecha: 2026-07-16

## Contexto

FASE 03 creó un ledger físico inmutable y `inventory_balances`, pero `reserved_stock` todavía no tenía comportamiento. Una verificación de disponibilidad sin bloqueo permitía que dos operaciones aceptaran las mismas unidades. Además, una salida física podía dejar el saldo por debajo de unidades prometidas.

## Decisión

- Una reserva es un agregado persistente con cabecera, items inmutables y eventos append-only.
- La reserva se crea únicamente para organizaciones cuyo rollout ledger está en `active`.
- Cada comando usa una clave idempotente única por organización y un hash canónico del payload. Repetir clave y payload devuelve el mismo resultado; reutilizar la clave con otro payload falla.
- Las reservas multi-ubicación bloquean `inventory_balances` por ID ascendente dentro de una sola transacción y validan todos los items antes de escribir.
- `available_stock = physical_stock - reserved_stock` y debe cumplirse `physical_stock >= reserved_stock >= 0`.
- `version` continúa siendo la secuencia del ledger físico. `reservation_version` registra cambios de reserva sin romper la conciliación de movimientos de FASE 03.
- Liberar o expirar resta únicamente `reserved_stock`; no mueve stock físico. Los estados `released` y `expired` son terminales.
- La expiración usa una clave determinística, se ejecuta por lote y procesa cada reserva en una transacción independiente.
- El ledger rechaza cualquier salida, ajuste o reverso que deje `physical_stock < reserved_stock`.
- Los reintentos de deadlock se configuran y se aplican en el límite transaccional. Los índices únicos siguen siendo la garantía de idempotencia.
- El rollout no puede salir de `active` mientras existan reservas activas. El rollback operativo exige liberarlas explícitamente.

## Orden de bloqueos

1. Cabecera de reserva cuando se ejecuta una transición terminal.
2. Saldos involucrados en orden ascendente por `inventory_balance_id`.
3. Escritura de items/eventos y actualización de saldos.

No se ejecutan APIs, correo ni colas dentro de estas transacciones.

## Expiración

El TTL predeterminado, máximo, tamaño de lote y número de reintentos se configuran por entorno. El scheduler ejecuta cada minuto `inventory:reservations-expire` con `withoutOverlapping`; este mutex es protección operativa y no reemplaza los locks ni constraints de base de datos.

## Rollout y rollback

1. Ejecutar backfill y conciliación de FASE 03.
2. Activar el ledger para la organización.
3. Habilitar productores de reservas en fases posteriores.
4. Para volver a `shadow/off`, ejecutar primero `inventory:reservations-release ID --all-active`.
5. Conciliar y luego cambiar el rollout.

## Consecuencias

- Se evita la sobreventa por carreras sobre el mismo saldo.
- La disponibilidad puede diferir del stock físico sin alterar el kardex.
- La auditoría permite reconstruir quién reservó y quién liberó o expiró.
- Checkout, POS, despacho, consumo de reservas y transferencias en tránsito no se integran aquí; corresponden a FASE 05/06.
- Los espejos legacy continúan representando stock físico, no reservas.

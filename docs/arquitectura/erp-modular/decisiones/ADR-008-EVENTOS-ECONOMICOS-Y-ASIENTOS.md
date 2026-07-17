# ADR-008 - Eventos económicos y asientos contables

## Estado

Aceptado el 2026-07-17.

## Contexto

El autoasiento anterior escribía directamente la cabecera y las líneas al emitir una venta. La deduplicación por referencia era vulnerable a concurrencia, no existía una transacción única y no se conservaba el hecho económico que originó el asiento. Además, costo de venta, cobros, notas de crédito, devoluciones físicas y reversión no tenían un flujo contable uniforme.

## Decisión

Se introduce `accounting_economic_events` como bandeja durable y append-only entre los módulos operativos y el libro contable.

- Cada productor registra un evento con organización, tipo, fuente, clave idempotente, hash SHA-256, payload congelado y fecha de ocurrencia.
- El productor inserta el evento en la misma transacción local que confirma el pago, emisión, despacho o devolución; solo el trabajo de procesamiento espera al commit.
- La unicidad `(organization_id, idempotency_key)` permite replay solo si el hash y la fuente coinciden.
- La fuente económica también es única por organización, tipo, clase e identificador.
- El procesamiento reclama el evento bajo bloqueo y crea cabecera, líneas, snapshot de configuración y vínculo 1:1 en una sola transacción.
- Los jobs transportan explícitamente `organization_id` y `event_id`, se despachan después del commit y son únicos por cinco minutos.
- Un fallo no elimina el evento: conserva intentos, código, mensaje y próxima fecha de reintento.
- Los asientos publicados nunca se editan ni eliminan. Toda corrección usa un evento de reversión y un asiento espejo.
- No se realiza backfill ni auto-post del histórico.

## Tipos y reglas

1. `invoice_issued`: débito a cuentas por cobrar y crédito a ingresos e impuesto.
2. `inventory_dispatched`: débito a costo de venta y crédito a inventario usando el costo inmutable del movimiento confirmado.
3. `payment_received`: débito a caja/bancos y crédito a cuentas por cobrar.
4. `credit_note_issued`: contraasiento proporcional del comprobante original; no mueve inventario.
5. `inventory_returned`: débito a inventario y crédito a costo de venta usando el costo confirmado de retorno.
6. `entry_reversal`: invierte exactamente cada línea del asiento original y lo enlaza sin modificarlo.

La cuenta de cada producto se resuelve campo por campo con prioridad producto → categoría → empresa. `NO_APLICA` excluye el producto; `MANUAL` o `PENDIENTE_CONFIGURACION` detienen el evento completo para evitar asientos parciales. Toda cuenta explícita debe existir, estar activa y pertenecer a la organización.

## Estados

`pending -> processing -> processed|error`

Un evento `error` puede volver a `pending`. Cuando un evento procesado recibe su reversión, pasa a `reversed`; el evento de reversión queda `processed` y conserva su propio asiento.

## Integridad y seguridad

- Índices únicos impiden eventos, asientos o reversiones duplicados.
- Triggers SQLite/MySQL protegen identidad y payload del evento, eliminación de evidencia, inmutabilidad de asientos publicados y coherencia tenant de líneas.
- La migración no baja si existe evidencia de Fase 8.
- La capacidad `accounting.general_ledger`, el estado activo de la organización y los periodos abiertos se verifican al procesar.
- Si el entitlement no está activo, el hecho puede quedar registrado y pendiente, pero no se publica ningún asiento.

## Consecuencias

Billing, Sales, Orders e Inventory dejan de ser escritores directos del libro. La contabilidad puede recuperarse de fallos de configuración o de cola sin duplicar asientos y conserva trazabilidad hasta la fuente. A cambio, la operación debe mantener un worker para la cola `accounting`, revisar la bandeja de errores y corregir cuentas antes de reintentar.

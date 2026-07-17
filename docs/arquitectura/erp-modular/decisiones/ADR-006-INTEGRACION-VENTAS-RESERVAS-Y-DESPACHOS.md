# ADR-006: Integracion de ventas, reservas y despachos

- Estado: aceptada
- Fecha: 2026-07-16
- Fase: 06

## Contexto

E-commerce y POS descontaban inventario fisico al confirmar comercialmente una venta. El comprobante tributario se creaba en la misma operacion del POS, por lo que agregar un despacho posterior habria duplicado la salida. Tampoco existian claves idempotentes de creacion, seleccion explicita de almacen ni estados independientes para venta, pago, tributo y almacen.

## Decision

La integracion activa por canal sigue este flujo:

1. La venta crea un `Order` idempotente y reserva el saldo del almacen predeterminado.
2. La solicitud de despacho crea un `InventoryDocument` tipo `dispatch` en borrador y no mueve existencias.
3. Solo la confirmacion del documento consume la reserva y registra la salida fisica.
4. Factura, boleta, emision, reintento o rechazo tributario no llaman servicios de inventario.
5. Una nota de credito es primero un hecho tributario. La entrada ocurre unicamente al confirmar un documento `customer_return` en almacen.
6. La cancelacion anterior al despacho libera la reserva; despues del despacho exige devolucion fisica.
7. La confirmacion de despacho o devolucion bloquea el pedido y confirma el documento dentro de una unica transaccion de base de datos.

El estado se separa de esta forma:

| Dimension | Fuente de verdad |
| --- | --- |
| Comercial | `orders.status` |
| Pago | `orders.payment_status` |
| Almacen | `orders.warehouse_status` y documentos de inventario |
| Tributaria | `billing_documents.status` |
| Contable | fuera del alcance de FASE 06 |

Los estados de almacen son `legacy_completed`, `not_required`, `reserved`, `dispatch_requested`, `dispatched`, `released`, `return_requested`, `returned` y `reservation_expired`.

## Idempotencia y concurrencia

- Venta: unique `(organization_id, sales_channel, idempotency_key)` mas `payload_hash`.
- Reserva: `sales-order:{order_id}:reserve`.
- Despacho: `sales-order:{order_id}:reservation:{reservation_version}:dispatch`.
- Cancelacion: `sales-order:{order_id}:cancel`.
- Nota de credito: unique tenant + clave y hash de contenido.
- Devolucion: `credit-note:{credit_note_id}:return`.
- Numeracion comercial: contador bloqueable por organizacion y serie, en lugar de depender solo de `MAX()+1`.
- Las transacciones reintentan deadlocks acotadamente y el replay posterior a una colision unique recupera el agregado existente.

FASE 06 admite despacho total. El documento de despacho debe coincidir exactamente con la reserva, conforme al contrato de FASE 05.

Una reserva vencida actualiza el pedido a `reservation_expired`, invalida cualquier borrador de despacho anterior y puede renovarse creando una nueva version. Esto evita reutilizar documentos o claves de una reserva obsoleta.

## Rollout y reversibilidad

`sales_inventory_channel_rollouts` controla independientemente `ecommerce` y `pos` con modos `legacy`, `shadow` y `active`.

- Sin fila configurada se conserva `legacy`.
- `active` exige ledger de inventario activo y almacen predeterminado activo.
- El downgrade se bloquea mientras existan reservas o solicitudes de despacho abiertas.
- `legacy` conserva el comportamiento anterior como rollback operativo.
- El `down()` de la migracion se bloquea si existen reservas o solicitudes de despacho abiertas, para no dejar stock reservado sin su agregado comercial; el rollback limpio fue verificado mediante rollback y reaplicacion.

## Limites de confianza

El checkout publico ya no acepta como fuente de verdad `payment_status`, `transaction_id`, serie, moneda, descuento, envio ni tasa tributaria. Estos valores se fijan o calculan del lado servidor; el pago queda pendiente hasta que lo confirme un canal confiable.

POS sigue siendo un canal administrativo autorizado, pero agrupa lineas repetidas por producto y rechaza precios diferentes para el mismo producto.

La confirmacion fisica de despachos y devoluciones usa permisos especificos de almacen (`inventory.dispatches.confirm` e `inventory.returns.confirm`). No basta con un permiso comercial generico.

Las notas de credito se crean en borrador y controlan que el acumulado no supere el comprobante original. Como el proveedor electronico actual aun no implementa ese tipo documental, FASE 06 solo permite marcarlas emitidas mediante evidencia externa verificable; no simula un envio al proveedor. Una devolucion fisica total exige que la nota emitida cubra el total del comprobante original.

## Consecuencias

- Crear una venta o comprobante deja el fisico intacto.
- El disponible disminuye por reserva y vuelve a aumentar al liberar.
- Cada producto despachado produce exactamente un movimiento outbound.
- Cada devolucion confirmada produce exactamente un movimiento inbound.
- Los reintentos tributarios no pueden duplicar movimientos.
- Los jobs de Billing resuelven la configuracion por la organizacion del documento, sin fallback a otro tenant.

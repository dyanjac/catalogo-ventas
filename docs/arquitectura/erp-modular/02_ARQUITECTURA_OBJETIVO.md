# Arquitectura objetivo — monolito modular ERP SaaS

## Límites funcionales

| Dominio | Responsabilidad | Integración |
|---|---|---|
| Catálogo | datos maestros, tipos y tributación de producto | contratos de lectura |
| Entitlements | plan, addon y capacidades por organización | síncrona para autorización; eventos de cambios |
| Inventario Core | saldos, reservas, movimientos y reversos | comandos transaccionales |
| Inventario Avanzado | almacenes, transferencias, lotes, series, reportes | capacidades de pago |
| Ventas | cotización/nota de venta, pedido y despacho solicitado | comandos y eventos |
| Billing | comprobante tributario y comunicación SUNAT | asíncrona, idempotente |
| Transporte | GRE y traslado | enlaza operación de almacén, no mueve stock por sí sola |
| Contabilidad | eventos económicos, reglas y asientos | consume eventos post-commit |

## Contratos y eventos

- Síncronos y transaccionales: `ReserveStock`, `ReleaseReservation`, `ConfirmDispatch`, `ConfirmReceipt`, `ReverseInventoryMovement`.
- Asíncronos e idempotentes: `BillingDocumentIssued`, `BillingDocumentAccepted`, `DispatchConfirmed`, `PaymentReceived`, `EconomicEventRecorded`.
- Externos: SUNAT, correo y APIs solo después del commit y sin locks de stock abiertos.
- Outbox: requerido antes de depender de entrega confiable entre módulos o hacia servicios externos.

## Inventario objetivo

1. `inventory_movements` será inmutable, con clave de idempotencia, motivo tipificado, referencia y vínculo de reverso.
2. Una proyección de saldo por organización, almacén, producto, lote y serie mantendrá `stock_fisico`, `stock_reservado`, `stock_en_transito` y `version`.
3. `stock_disponible = stock_fisico - stock_reservado`; no habrá endpoint genérico de edición directa.
4. Saldos y movimiento se actualizarán en la misma transacción con orden estable de bloqueos, reintentos limitados y conciliación periódica.
5. Stock inicial y ajustes serán documentos aprobados que generan movimientos.

## Productos y contabilidad

- Producto: identidad, comercial, tributario y tipo (`bien_fisico`, `servicio`, `suscripcion`, `digital`, `kit`, `informativo`).
- Inventario y costo no serán campos editables del mantenimiento normal.
- Configuración contable: producto → categoría → empresa → bandeja de revisión.
- Tratamiento contable: `HEREDAR`, `AUTOMATICO`, `MANUAL`, `NO_APLICA`, `PENDIENTE_CONFIGURACION`.
- Los eventos económicos se preservan aunque Contabilidad no esté contratada; los asientos publicados se revierten, no se eliminan.

## Estados objetivo

- Comercial: borrador, confirmado, cancelado.
- Almacén: solicitado, reservado, preparado, despachado, recibido, revertido.
- Tributario: borrador, emitido, pendiente_envio, enviado, aceptado, rechazado, anulado.
- Contable: pendiente, procesado, error, reversado.

## Migración, compatibilidad y rollback

- Añadir tablas/campos sin eliminar rutas actuales en la primera entrega.
- Backfill idempotente y conciliación antes de activar la nueva fuente de saldo.
- Doble lectura temporal solo con métrica de divergencia; no doble escritura indefinida.
- Feature flags/entitlements para la activación progresiva por organización.
- Cada migración tendrá `down()` y cada cambio de datos tendrá procedimiento de reversión o compensación documentado.

## Auditoría y pruebas

- Auditoría de actor, organización, referencia e idempotency key en operaciones críticas.
- Pruebas unitarias de reglas y feature tests para flujos.
- Pruebas concurrentes para reservas, despacho y transferencias.
- Conciliación kardex ↔ saldos, documento ↔ movimiento y evento ↔ asiento.

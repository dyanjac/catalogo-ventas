# Diagnóstico del estado actual

## Qué existe y funciona parcialmente

- Modularización real con `nwidart/laravel-modules` y providers por módulo.
- Tenancy por `organizations`, context service y scopes explícitos.
- RBAC con módulos, roles y permisos.
- Inventario por sucursal y almacén, movimientos, documentos internos y transferencias.
- Emisión electrónica con proveedores, colas configurables, XML/CDR e historial de respuestas.
- Plan de cuentas, períodos, asientos manuales y autoasiento básico de venta.

## Hallazgos

| Severidad | Hallazgo y evidencia | Riesgo / acción requerida |
|---|---|---|
| CRÍTICO | `OrderCheckoutService::checkout()` descuenta stock al crear pedido `confirmed`. | Confunde compromiso comercial con despacho físico; reemplazar por reserva idempotente. |
| CRÍTICO | No existen reservas, `stock_reservado`, expiración ni `stock_en_transito`. | Sobreventa y falta de trazabilidad operativa. |
| ALTO | `products.stock`, `product_branch_stocks.stock` y `product_warehouse_stocks.stock` coexisten. `ProductInventoryService` intenta sincronizarlos. | Tres fuentes de verdad potencialmente divergentes. |
| ALTO | Solicitudes de producto aceptan `stock`, `min_stock`, `average_price` y cuentas contables. | Cambios no auditables de saldo/costo y acoplamiento del catálogo. |
| ALTO | `inventory_movements` permite actualización/eliminación a nivel de modelo/base y no tiene idempotency key. | Kardex no inmutable y movimientos duplicables. |
| ALTO | Transferencias se crean `completed` y aplican origen/destino sin tránsito ni recepción. | No representa la custodia logística. |
| ALTO | RBAC controla acceso de usuario, pero no hay planes, addons, suscripciones ni entitlements tenant-aware. | No se puede separar capacidad vendida de necesidad técnica interna. |
| ALTO | No existe entidad ni flujo GRE remitente/transportista; solo un tipo de operación SUNAT menciona guía. | Riesgo tributario y logístico. |
| ALTO | `SalesPosController` orquesta directamente catálogo, pedidos, billing y contabilidad. | Límites de módulos frágiles y alto acoplamiento. |
| ALTO | `SalesAccountingService` genera solo CxC/ingresos/IGV. | No registra costo de venta, cobranza, compras, devoluciones ni devengamientos. |
| ALTO | La prevención de duplicidad contable consulta `reference`, sin índice único observado. | Carrera concurrente y asientos duplicados. |
| MEDIO | La emisión exitosa crea asiento; confirmación tributaria definitiva es **NO CONFIRMADA**. | Política contable/tributaria ambigua. |
| MEDIO | No hay outbox, eventos de dominio ni entrega posterior al commit estandarizada. | Reintentos y consistencia intermodular débiles. |
| MEDIO | El scope de organización es explícito, no global; las relaciones cruzadas no se validan por tenant. | Riesgo de fuga de datos ante nuevos queries sin scope. |
| MEDIO | No se hallaron pruebas de inventario, concurrencia, GRE, facturación o contabilidad. | Regresiones no detectadas. |
| MEDIO | `vendor/autoload.php` falta y PHP CLI no tiene `ext-ldap`. | Línea base automatizada no ejecutable en este entorno. |
| BAJO | Códigos de documentos se derivan de `max(id)+1`. | Colisiones o huecos bajo concurrencia; migrar a secuencias/idempotencia. |
| INFORMATIVO | Existen documentos previos de inventario, contabilidad y tenancy. | Deben tratarse como históricos: el código es la fuente de verdad. |

## Deuda y contradicciones funcionales

El producto mezcla información maestra, comercial, de inventario y contable. La prioridad de herencia contable producto → categoría → empresa no existe. El booleano `requires_accounting_entry` no modela HEREDAR, AUTOMÁTICO, MANUAL, NO_APLICA ni PENDIENTE_CONFIGURACION.

El movimiento y el saldo se actualizan en transacciones locales con bloqueos pesimistas en varias rutas, pero faltan orden global de locks, reintentos por deadlock, reservas y claves de idempotencia. Existe una base útil, no un núcleo de inventario completo.

## Riesgos por dominio

- **Datos:** divergencia entre saldos agregados, sucursales y almacenes; backfills históricos no conciliados.
- **Concurrencia:** validación previa de disponibilidad en POS y transferencias; ausencia de reserva y reintentos.
- **Tributario:** GRE no implementada; estados de comprobante no expresan claramente aceptación, rechazo y anulación conforme al flujo objetivo.
- **Contable:** asientos sin eventos económicos, reversos ni control de configuración pendiente.
- **Acoplamiento:** dependencias directas cruzadas y orquestación en controlador.
- **Activación de módulos:** no existe distinción entre suscripción comercial y persistencia técnica obligatoria.

## Estado de pruebas de línea base

- Suite configurada: 11 archivos de prueba observados entre `tests/` y módulos.
- Resultado: **NO CONFIRMADO / BLOQUEADO**.
- Causa: `vendor/autoload.php` no estaba disponible; `composer install` exige `ext-ldap`, ausente en PHP CLI.
- No se concluye que las pruebas estén fallando; solo que no se pudieron ejecutar en este entorno.

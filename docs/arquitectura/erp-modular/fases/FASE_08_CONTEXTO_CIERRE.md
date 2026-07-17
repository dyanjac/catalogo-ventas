# FASE 08 - Contexto de cierre

## Resultado

FASE 08 implementa eventos económicos idempotentes y su procesamiento contable para comprobantes, costo de venta, cobros, notas de crédito, devoluciones físicas y reversión. Los módulos operativos registran hechos; solo el procesador contable publica asientos.

## TERMINADO

- Tabla durable `accounting_economic_events` con estados pendiente, procesando, procesado, error y revertido.
- Idempotencia por organización y hash de payload, además de unicidad de fuente económica.
- Procesamiento transaccional bajo bloqueo y vínculo evento/asiento 1:1.
- Job único `ProcessEconomicEventJob`, despacho after-commit y cola `accounting` configurable.
- Comando `accounting:events:retry` para reencolar pendientes y errores vencidos.
- Asiento de comprobante: CxC contra ingresos e IGV.
- Asiento de costo de venta desde el costo inmutable del despacho confirmado.
- Asiento de cobro: caja/bancos contra CxC.
- Nota de crédito como contraasiento proporcional del comprobante original.
- Devolución física: inventario contra costo de venta.
- Reversión exacta mediante asiento espejo, sin alterar evidencia original.
- Resolución contable producto → categoría → empresa, con snapshot por evento.
- Cuenta de caja/bancos incorporada a la configuración empresarial.
- Plan mínimo ampliado con caja, inventario y costo de venta.
- Cuentas explícitas validadas como activas y pertenecientes al tenant.
- Eventos registrados desde Billing, POS, pago administrativo, despacho y devolución.
- Outbox transaccional: el hecho económico y su evento se confirman o revierten juntos; el job sale después del commit.
- Asientos publicados y sus líneas protegidos en Eloquent y mediante triggers SQLite/MySQL.
- Rollback bloqueado cuando existe evidencia económica de Fase 8.
- Bandeja administrativa con filtros, detalle, error, reintento y reversión.
- Permisos `accounting.events.view`, `accounting.events.process` y `accounting.events.reverse`.
- ADR-008 y pruebas funcionales/concurrentes de la fase.

## EN PROGRESO

- Ningún entregable de código de FASE 08 queda en progreso.
- La activación es operativa por empresa: configurar cuentas, habilitar auto-post y mantener el worker de la cola.

## NO INICIADO

- Backfill contable histórico; se excluye intencionalmente para no publicar hechos anteriores sin conciliación.
- Pagos parciales o múltiples por pedido; el modelo comercial actual conserva un solo estado/importe de pago.
- Distribución por producto de notas de crédito parciales para revertir costo de venta sin devolución física.
- Compras, provisiones, detracciones, percepciones y tipos de cambio.
- Libros electrónicos, mayor, balance de comprobación y estados financieros.

## BLOQUEADO

- No hay bloqueos para operar la fase con SQLite o una base migrada.
- La validación concurrente real MySQL/InnoDB queda como prueba opt-in porque el entorno automatizado actual usa SQLite.

## Archivos clave

- `Modules/Accounting/database/migrations/2026_07_17_080000_create_accounting_economic_event_domain.php`
- `Modules/Accounting/app/Services/EconomicEventService.php`
- `Modules/Accounting/app/Jobs/ProcessEconomicEventJob.php`
- `Modules/Accounting/app/Models/AccountingEconomicEvent.php`
- `Modules/Accounting/app/Http/Controllers/AccountingEconomicEventController.php`
- `tests/Feature/AccountingEconomicEventWorkflowTest.php`
- `tests/Feature/AccountingMysqlConcurrencyTest.php`
- `docs/arquitectura/erp-modular/decisiones/ADR-008-EVENTOS-ECONOMICOS-Y-ASIENTOS.md`

## Validación ejecutada

- Sintaxis PHP aprobada y Laravel Pint aplicado.
- Cuatro rutas administrativas de eventos registradas.
- Comando `accounting:events:retry` registrado.
- Suite FASE 08: 6 pruebas, 35 aserciones, aprobada.
- Regresión focal Accounting + Sales + Inventory: 16 pruebas, 113 aserciones, aprobada.
- Concurrencia MySQL/InnoDB implementada como prueba opt-in; omitida en SQLite.
- Suite global: 102 pruebas totales, 5 fallos históricos no relacionados, 4 pruebas MySQL opt-in omitidas y 432 aserciones.

Los cinco fallos globales preexistentes son los mismos documentados al cierre de FASE 07: redirección de invitado admin, dos accesos super-admin bloqueados por RBAC, reset de paleta suspendida y `ExampleTest` sin migraciones para `security_branches`. Las pruebas de FASE 08 y sus regresiones focales no presentan fallos.

## Operación y activación

1. Ejecutar migraciones.
2. Configurar cuentas activas para ingresos, CxC, IGV, caja/bancos, inventario y costo de venta.
3. Marcar productos/categorías como `AUTOMATICO` o `NO_APLICA` según corresponda.
4. Habilitar auto-post en la configuración contable.
5. Mantener un worker para `accounting`: `php artisan queue:work --queue=accounting,default`.
6. Vigilar Administración > Contabilidad > Eventos económicos.
7. Tras corregir configuración, reintentar desde la UI o ejecutar `php artisan accounting:events:retry --organization=ID`.

## Próxima fase

FASE 09 puede abordar suscripciones, facturación recurrente y devengamiento, usando eventos económicos nuevos sin escribir asientos directamente desde el módulo comercial.

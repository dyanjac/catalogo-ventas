# FASE 06 - Contexto de cierre

## Resultado

FASE 06 integra e-commerce, POS, facturacion e inventario sobre el ledger de FASE 04/05. La venta reserva, el despacho confirmado mueve fisico y el comprobante tributario permanece aislado del almacen.

## Entregables implementados

- Rollout tecnico independiente por organizacion y canal: `legacy`, `shadow`, `active`.
- Idempotencia durable de checkout y POS con deteccion de conflicto de payload.
- Contadores de pedido bloqueables por organizacion y serie.
- Seleccion del almacen predeterminado y del balance exacto para cada producto fisico.
- Reserva atomica al crear venta integrada.
- Solicitud de despacho sin movimiento.
- Confirmacion idempotente de despacho con consumo de reserva.
- Cancelacion idempotente anterior al despacho.
- Nota de credito relacionada e idempotente, sin efecto automatico en inventario.
- Limite acumulado de notas de credito y registro de emision externa con evidencia verificable.
- Solicitud y confirmacion de devolucion fisica mediante `customer_return`.
- Devolucion fisica total solo con nota de credito emitida por el total del comprobante.
- Estados comercial, pago, almacen y tributario separados.
- Sincronizacion del vencimiento de reserva, renovacion versionada y descarte del despacho obsoleto.
- Acciones administrativas para solicitar/confirmar despacho, renovar reserva y confirmar devolucion.
- Permisos fisicos especificos para despacho y devolucion, separados de los permisos comerciales.
- Checkout publico endurecido: pago, descuentos, envio, impuesto, serie y moneda dejan de ser controlables por el cliente.
- Resolucion tenant-safe de configuracion de Billing y guardas contra reemitir documentos emitidos o anulados.
- Modo `legacy` conservado como rollback por canal.

## Archivos clave

- `database/migrations/2026_07_16_180000_integrate_sales_inventory_workflow.php`
- `Modules/Orders/app/Services/OrderInventoryLifecycleService.php`
- `Modules/Orders/app/Services/SalesInventoryChannelRolloutService.php`
- `Modules/Orders/app/Services/OrderCheckoutService.php`
- `Modules/Sales/app/Http/Controllers/SalesPosController.php`
- `Modules/Billing/app/Services/BillingCreditNoteService.php`
- `tests/Feature/SalesInventoryWorkflowTest.php`
- `tests/Feature/SalesInventoryMysqlConcurrencyTest.php`
- `tests/Support/phase06_checkout_worker.php`
- `tests/Support/phase06_dispatch_worker.php`
- `docs/arquitectura/erp-modular/decisiones/ADR-006-INTEGRACION-VENTAS-RESERVAS-Y-DESPACHOS.md`

## Validacion ejecutada

- Migracion completa SQLite: aprobada.
- Rollback y reaplicacion limpia de migracion FASE 06: aprobados.
- Guarda de rollback con operaciones abiertas en MySQL: aprobada; la migracion rehusa eliminar el contrato mientras haya reservas o despachos pendientes.
- Suite funcional FASE 06 + checkout: 9 pruebas, 85 aserciones, aprobada.
- Concurrencia MySQL/InnoDB opt-in: 1 prueba, 19 aserciones, aprobada.
  - dos checkouts de 6 sobre stock 10: solo uno reserva;
  - dos solicitudes con la misma clave: un pedido y una reserva;
  - dos confirmaciones simultaneas: un documento y un movimiento;
  - dos ventas POS simultaneas: numeros distintos y reserva exacta sin descontar fisico.
- Suite global: 86 pruebas y 348 aserciones; 79 aprobadas, 5 fallos historicos no relacionados y 2 pruebas MySQL opt-in omitidas.
- Laravel Pint sobre archivos FASE 06: aprobado.
- `git diff --check`: aprobado.
- Compilacion Vite: aprobada. El entorno advierte que Node 18.20.8 debe actualizarse a 20.19+ o 22.12+.
- Compilacion de vistas Blade y registro de las ocho rutas administrativas de pedidos: aprobados.

## Fallos globales historicos fuera del alcance

- Redireccion de invitado admin espera `/login`, aplicacion usa `/admin/login`.
- Acceso super-admin historico recibe 403 por RBAC en dos pruebas.
- Reset de paleta para tenant suspendido redirige al login admin.
- `ExampleTest` no migra tablas y consulta `security_branches`.

El fallo historico de `CheckoutTest` por fixture sin stock de sucursal fue corregido al alinear el test con el contrato real.

## Operacion y activacion

La migracion no activa automaticamente los canales. Debe activarse por organizacion despues de validar ledger, conciliacion, sucursal y almacen predeterminado:

```php
app(\Modules\Orders\Services\SalesInventoryChannelRolloutService::class)
    ->setMode($organizationId, 'ecommerce', \Modules\Orders\Enums\SalesInventoryChannelMode::Active);
```

Repetir para `pos`. Mientras el modo sea `legacy`, el flujo anterior sigue operativo y no se crean reservas FASE 06.

## Limites conscientes

- El despacho parcial queda para una evolucion posterior; FASE 06 confirma la reserva completa.
- El pago aprobado debe provenir de una integracion confiable; el checkout publico solo crea pago pendiente.
- El proveedor electronico actual no emite notas de credito. FASE 06 las crea en borrador y admite registrar emision externa con evidencia; la integracion nativa queda para FASE 07.
- La UI completa de emision de notas de credito puede evolucionar en la fase tributaria; el servicio y el contrato de devolucion ya impiden mover stock antes de la recepcion fisica.

## Proxima fase

FASE 07 puede abordar documentos electronicos adicionales y GRE, conservando la regla de que el documento tributario nunca es el origen directo de un movimiento de inventario.

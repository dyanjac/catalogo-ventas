# FASE 05 — Contexto de cierre

## 1. Identificación

- Fase: 05
- Nombre: Operaciones avanzadas de inventario
- Fecha de cierre: 2026-07-16
- Estado: implementada y validada
- Base recibida: FASE 03 en `42d90bf` más FASE 04 pendiente de commit, rama `master`

## 2. Objetivo cumplido

Se implementaron despacho, recepción, notas de ingreso y salida, ajustes, devoluciones, consumo físico de reservas, transferencias con stock en tránsito, recepciones parciales, idempotencia durable, conciliación y reversos compensatorios sin duplicar el ledger.

## 3. Alcance implementado

- Tipos documentales `inbound`, `outbound`, `opening_stock`, `stock_adjustment`, `dispatch`, `receipt`, `customer_return`, `supplier_return` y `compensation`.
- Estados documentales tipados e inmutabilidad de documentos confirmados.
- Consumo total de reservas con estado/evento `consumed` y salida física atómica.
- Transferencias por almacén con estados `draft`, `in_transit`, `partially_received`, `received` y `cancelled`.
- Eventos y líneas de evento append-only para creación, despacho, recepción y cancelación.
- `in_transit_stock` y `transit_version` independientes del stock físico y reservado.
- Recepciones parciales y múltiples con validación de cantidad pendiente.
- Idempotencia con clave por organización y hash de payload en documentos, transferencias y eventos.
- Reversos compensatorios para documentos confirmados; el original permanece inmutable.
- Conciliación del tránsito contra transferencias abiertas.
- Bloqueo de downgrade mientras exista tránsito o transferencias abiertas.
- Interfaz Livewire para operaciones locales, despacho de transferencias y recepción parcial por destino.
- Permisos RBAC separados `inventory.transfers.create`, `inventory.transfers.dispatch` e `inventory.transfers.receive`.

## 4. Fuera de alcance respetado

- Integración con pedidos, checkout, POS o e-commerce.
- Emisión de comprobantes o descuento de stock por facturación.
- GRE remitente/transportista y comunicación SUNAT.
- Asientos contables automáticos por inventario.
- Lotes, series, vencimientos y FEFO.
- Eliminación de los espejos legacy.

## 5. Invariantes

1. `physical_stock >= reserved_stock >= 0` e `in_transit_stock >= 0`.
2. Consumir una reserva reduce físico y reservado por la misma cantidad dentro de una sola transacción.
3. Una reserva consumida no puede liberarse, expirar ni consumirse otra vez.
4. Despacho de transferencia: origen físico `-q`, destino tránsito `+q`.
5. Recepción: destino físico `+q`, destino tránsito `-q`.
6. La recepción acumulada nunca supera la cantidad despachada.
7. Documentos confirmados y eventos son inmutables.
8. Repetir clave y payload devuelve el mismo resultado; reutilizar la clave con otro contenido falla.
9. Organización, almacenes, saldos, productos y líneas deben pertenecer al mismo tenant.
10. Los saldos legacy reflejan stock físico y nunca stock reservado o en tránsito.

## 6. Migración y rollback

La migración `2026_07_16_170000_create_inventory_warehouse_operations.php`:

- extiende saldos, reservas, documentos, líneas y transferencias;
- crea eventos y líneas de evento de transferencia;
- añade claves foráneas, índices únicos e índices operativos;
- instala triggers SQLite y MySQL/MariaDB para invariantes, tenant e inmutabilidad;
- elimina triggers, tablas, claves e índices en orden seguro en `down()`.

Rollback operativo:

1. Completar o corregir transferencias abiertas.
2. Verificar `in_transit_stock = 0` mediante conciliación.
3. Confirmar que no existen reservas activas.
4. Cambiar rollout únicamente después de esas comprobaciones.
5. Ejecutar rollback de esquema solo con la aplicación detenida y respaldo validado.

## 7. Archivos principales

- `Modules/Catalog/database/migrations/2026_07_16_170000_create_inventory_warehouse_operations.php`
- `Modules/Catalog/app/Data/InventoryTransfer*.php`
- `Modules/Catalog/app/Entities/InventoryTransfer*.php`
- `Modules/Catalog/app/Enums/InventoryDocument*.php`
- `Modules/Catalog/app/Enums/InventoryTransfer*.php`
- `Modules/Catalog/app/Services/InventoryDocumentService.php`
- `Modules/Catalog/app/Services/InventoryReservationService.php`
- `Modules/Catalog/app/Services/InventoryTransferService.php`
- `Modules/Catalog/app/Services/InventoryLedgerService.php`
- `Modules/Catalog/app/Services/InventoryReconciliationService.php`
- `app/Livewire/Admin/InventoryIndex.php`
- `resources/views/livewire/admin/inventory-index.blade.php`
- `tests/Feature/InventoryWarehouseOperationsTest.php`
- `tests/Feature/InventoryWarehouseOperationsMysqlConcurrencyTest.php`

## 8. Validaciones ejecutadas

- `php vendor/bin/pint --dirty`: PASS.
- Compilación de vistas Blade: PASS.
- PHPUnit enfocado FASE 03 + FASE 04 + FASE 05: 37 pruebas, 130 aserciones, PASS; una prueba MySQL opt-in omitida bajo SQLite.
- Migración completa desde cero sobre MySQL 8.4: PASS.
- Rollback y reaplicación de FASE 05 sobre MySQL 8.4: PASS.
- Carrera MySQL/InnoDB de dos recepciones 6+6 sobre tránsito 10: una confirma y otra se rechaza; recibido 6, tránsito 4.
- Carrera MySQL/InnoDB de dos recepciones 5+5 sobre tránsito 10: ambas confirman; recibido 10, tránsito 0 para esa transferencia.
- Carrera MySQL/InnoDB de dos transferencias distintas al mismo destino: ambos despachos confirman y el tránsito acumula ambas cantidades sin lost update.
- Carrera MySQL/InnoDB de dos reversos idénticos: ambos procesos reciben el mismo resultado y existe un único documento compensatorio.
- Prueba MySQL opt-in: 1 prueba, 14 aserciones, PASS.
- Suite global: 78 pruebas, 71 pasan, 6 fallan y 1 MySQL se omite; las 6 fallas son históricas y ajenas a FASE 05.
- Revisión sniper independiente: 2 hallazgos P1 y 5 P2 corregidos y revalidados.
- `composer validate --no-check-publish`: PASS.
- `npm run build`: PASS; el runtime local Node 18 queda por debajo de la versión recomendada por Vite 7.

## 9. Cobertura enfocada

- consumo atómico e idempotente de reserva por despacho;
- devolución de cliente y reverso compensatorio;
- despacho, tránsito, recepción parcial, replay y finalización;
- sobre-recepción con rollback completo;
- cancelación exclusiva de borrador;
- bloqueo de downgrade con tránsito;
- creación documental y de transferencia idempotente, incluido conflicto de payload;
- drift de conciliación de tránsito;
- inmutabilidad SQL de documentos y eventos;
- concurrencia real MySQL sin sobre-recepción ni lost updates.
- idempotencia concurrente del reverso compensatorio;
- aislamiento tenant SQL de documentos, líneas, transferencias y saldos;
- separación RBAC entre creación, despacho y recepción.

## 10. Decisiones y riesgos

- MySQL/InnoDB es la referencia para concurrencia. SQLite valida reglas funcionales, no locks de fila.
- Las cantidades acumulables se releen con `FOR UPDATE` después de bloquear la cabecera para evitar snapshots antiguos bajo `REPEATABLE READ`.
- El incremento de tránsito del despacho usa una actualización SQL atómica sobre la fila bloqueada; un `refresh()` no bloqueante podría recuperar un snapshot antiguo.
- La interfaz heredada crea y despacha usando el almacén predeterminado activo de cada sucursal; no selecciona almacenes arbitrarios.
- Una corrección posterior al despacho debe ser explícita y auditable; no se permite cancelar una transferencia en tránsito.
- FASE 06 debe decidir en qué evento de pedido se crea y consume la reserva, sin hacer que el comprobante fiscal descuente stock otra vez.

Documento de decisión: `docs/arquitectura/erp-modular/decisiones/ADR-005-OPERACIONES-DE-ALMACEN-Y-TRANSITO.md`.

## 11. Criterios de aceptación

- [x] Despacho y recepción físicos.
- [x] Notas de ingreso, salida, ajustes y devoluciones.
- [x] Consumo atómico de reservas.
- [x] Transferencias con stock en tránsito.
- [x] Recepciones parciales y conservación de cantidades.
- [x] Idempotencia y estados terminales.
- [x] Reversos compensatorios e inmutabilidad.
- [x] Conciliación y bloqueo de rollback inseguro.
- [x] Interfaz y permiso de recepción por destino.
- [x] Pruebas funcionales y carreras reales MySQL.
- [x] Revisión independiente sin hallazgos P1/P2 pendientes.

## 12. Próxima fase recomendada

- Código: FASE 06.
- Nombre: Integración de ventas, POS y e-commerce.
- Objetivo: pedido reserva; despacho consume reserva y mueve stock; comprobante no descuenta inventario.
- Condición cumplida: revisión independiente, suite global y validación MySQL cerradas.

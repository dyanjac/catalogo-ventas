# Modulo de Contabilidad - Hoja de ruta

## Estado actual (base implementada)

### 1) Plan de cuentas
- Implementado:
  - Tabla `accounting_accounts`.
  - CRUD basico en Admin (`/admin/accounting/accounts`).
  - Jerarquia via `parent_id` y `level`.
  - Cuentas activas/inactivas.
  - Flags de cuentas por defecto para ventas/compras/impuestos.

### 2) Registro de asientos contables
- Implementado:
  - Listado y edicion de asientos (`/admin/accounting/entries`).
  - Validacion de partida doble (`Debe == Haber`).
  - Centro de costo por linea.
  - Adjuntos de respaldo por asiento.
  - Auditoria basica de cambios y eliminacion de adjuntos.
- Pendiente:
  - Registro manual de asientos nuevos (pantalla create/store).
- Implementado en FASE 08:
  - Reversión asistida con evento y asiento espejo automático.
  - Inmutabilidad de asientos publicados y líneas.
  - Vínculo 1:1 con el evento económico que originó el asiento.

### 3) Gestion de periodos contables
- Implementado:
  - Tabla `accounting_periods`.
  - CRUD basico en Admin (`/admin/accounting/periods`).
  - Apertura/cierre por periodo.
  - Bloqueo de modificaciones en asientos para periodos cerrados.
- Pendiente:
  - Cierre anual asistido.
  - Reglas de cierre mas estrictas (sin desbalance, sin borradores, etc.).

### 4) Centros de costo
- Implementado:
  - Tabla `accounting_cost_centers`.
  - CRUD basico en Admin (`/admin/accounting/cost-centers`).
  - Asociacion de centro de costo por linea de asiento.
- Pendiente:
  - Reportes por centro de costo.

### 5) Configuracion contable
- Implementado:
  - Pantalla de configuracion (`/admin/accounting/settings`):
    - moneda,
    - ejercicio fiscal,
    - mes de inicio,
    - cierre por periodos,
    - auto-posting.
- Pendiente:
  - Tipos de cambio.
  - Reglas contables por tipo de comprobante.

## Fase siguiente recomendada

### 6) Integracion con otros modulos (implementada en FASE 08)
- Eventos económicos idempotentes para comprobante, costo de venta, cobro, nota de crédito, devolución física y reversión.
- Resolución y snapshot de cuentas desde producto, categoría y empresa.
- Procesamiento transaccional en cola, reintentos y bandeja operativa.
- Compras y provisiones permanecen pendientes.

### 7) Libros contables y estados financieros
- Libro Diario.
- Libro Mayor.
- Registro de ventas/compras.
- Balance de comprobacion.
- Balance General y Estado de Resultados.
- Exportacion PDF/Excel.

### 8) Conciliacion bancaria
- Importar movimientos bancarios (CSV/Excel).
- Matching semiautomatico contra asientos.
- Registro de diferencias.

### 9) Auditoria avanzada
- Bitacora por entidad y usuario con diff estructurado.
- Filtros por fecha, usuario, modulo y accion.

## Criterios tecnicos para avanzar
- Mantener compatibilidad con modularizacion `nwidart/laravel-modules`.
- Aislar logica en servicios para evitar controladores grandes.
- Cubrir cada feature contable nueva con pruebas feature/integration.

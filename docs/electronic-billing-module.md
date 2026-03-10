# Módulo de facturación electrónica (Perú)

## Alcance
- Módulo `Billing` activable/desactivable.
- Proveedor seleccionable:
  - `greenter` (integración SUNAT/OSE vía librería PHP),
  - `nubefact` (API),
  - `tefacturo` (API),
  - `efact` (API).
- Pantallas admin:
  - `/admin/billing/settings`
  - `/admin/billing/documents`

## Base de datos
- `billing_settings`: configuración general y credenciales por proveedor.
- `billing_documents`: trazabilidad de comprobantes electrónicos emitidos.

## Notas de integración
- Greenter:
  - El módulo valida presencia de credenciales.
  - Para emisión real, instalar paquete:
    - `composer require greenter/greenter`
- APIs externas:
  - Se almacenan URL/credenciales por proveedor.
  - El diseño está preparado para implementar envío real en `issueDocument()`.

## Siguiente paso recomendado
- Conectar emisión automática desde pedidos/comprobantes:
  - al confirmar venta, crear `billing_documents`,
  - enviar por proveedor activo,
  - guardar CDR/ticket/respuesta en la tabla.

# Módulo Sales (Punto de Venta)

## Objetivo
- Registrar ventas desde Admin como:
  - Pedido (`order`)
  - Boleta (`boleta`)
  - Factura (`factura`)

## Flujo implementado
1. Operador registra cliente, ítems y forma de pago en `/admin/sales/pos`.
2. Se crea `orders` + `order_items` y se descuenta stock.
3. Si tipo es `boleta` o `factura`:
   - se crea `billing_documents` en estado `queued`,
   - se genera XML electrónico,
   - se envía al proveedor configurado en módulo Billing.

## Dependencias
- Requiere módulo `Billing` activo y configurado para emisión electrónica.

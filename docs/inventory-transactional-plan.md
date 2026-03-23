# Inventario Transaccional por Almacen

## Objetivo

Formalizar inventario con:

- almacenes por sucursal
- guias de ingreso y salida
- movimientos de kardex trazables
- control transaccional para evitar concurrencia
- costo promedio valido y verificable por almacen

## Reglas base

1. Ninguna guia confirmada debe actualizar stock fuera de una transaccion DB.
2. Toda confirmacion debe bloquear los registros de stock afectados con `lockForUpdate()`.
3. El stock operativo ya no se interpreta solo por sucursal; la fuente real debe ser `producto + sucursal + almacen`.
4. Todo movimiento confirmado debe dejar huella en kardex con:
   - producto
   - sucursal
   - almacen
   - tipo de movimiento
   - cantidad
   - stock antes
   - stock despues
   - costo promedio antes
   - costo unitario aplicado
   - costo promedio despues
   - codigo de referencia unico
5. El costo promedio por almacen se recalcula solo en operaciones confirmadas.
6. Una salida no puede confirmarse si deja stock negativo en el almacen afectado.

## Modelo funcional inicial

### Almacenes

- pertenecen a una sucursal
- tienen codigo unico por sucursal
- pueden marcarse como almacen por defecto

### Guias de inventario

Tipos iniciales:

- `inbound`
- `outbound`

Estados iniciales:

- `draft`
- `confirmed`
- `cancelled`

### Stock por almacen

Cada registro conserva:

- stock
- stock minimo
- costo promedio actual
- ultimo costo registrado

## Formula de costo promedio

Para guias de ingreso confirmadas:

`nuevo_costo_promedio = ((stock_actual * costo_promedio_actual) + (cantidad_ingreso * costo_unitario_ingreso)) / nuevo_stock`

Para guias de salida confirmadas:

- el costo promedio no se recalcula por nueva compra
- el costo unitario del movimiento se toma del costo promedio vigente del almacen
- el costo promedio final permanece igual salvo reglas futuras de ajuste valorizado

## Fases tecnicas

1. Crear tablas de almacenes, stock por almacen y documentos de inventario.
2. Extender kardex para incluir almacen y trazabilidad de costos.
3. Implementar servicios transaccionales de confirmacion documental.
4. Construir UI Livewire + Flux para guias y kardex.
5. Integrar RBAC y pruebas.

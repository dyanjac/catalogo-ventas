# Política ERP de precisión y redondeo — fase de backend pendiente

Estado: especificación objetivo. La rama `feature/responsive-pos-entry` conserva temporalmente los límites que puede registrar el servidor: cantidades de servicios con hasta 3 decimales, cantidades de productos con inventario enteras y precios unitarios con hasta 2 decimales. Esta rama no implementa todavía la matriz objetivo ni el selector de redondeo.

## Matriz objetivo

| Dato | Escala | Almacenamiento objetivo | Regla |
| --- | ---: | --- | --- |
| Precio y costo unitario | 6 | `DECIMAL(18,6)` | Conservar las 6 cifras en cálculo y persistencia; no redondear al mostrar un total comercial. |
| Cantidad de ítem, stock, reserva y movimiento | 4 | `DECIMAL(18,4)` | Admitir fracciones también en productos físicos y mantener la misma escala en todo el ciclo de inventario. |
| Subtotal de línea, descuento e impuesto | 2 | `DECIMAL(18,2)` | Calcular el subtotal de **cada línea** desde cantidad × precio y redondearlo antes de sumarlo o calcular impuestos. |
| Subtotal, impuesto y total de documento | 2 | `DECIMAL(18,2)` | Sumar importes de líneas ya redondeados y conciliar la distribución de descuentos e impuestos. |
| Cobros, pagos y asientos monetarios | 2 | `DECIMAL(18,2)` o unidades menores enteras | El importe liquidado debe coincidir exactamente con el total del documento. |

No usar columnas `FLOAT`/`DOUBLE` para dinero ni stock. El cálculo autoritativo debe operar con valores decimales exactos; convertir columnas `DECIMAL` a `float` en PHP antes de multiplicar o comparar reintroduce errores aunque el esquema sea exacto. No truncar el producto cantidad × precio antes del redondeo de línea.

## Redondeo configurable

La configuración global define `half_up` o `half_even`; una sucursal puede sobrescribirla. El método efectivo debe quedar registrado en cada documento emitido para que una modificación posterior de la configuración no cambie la reproducción de documentos históricos. Si no hay sobrescritura, se aplica el valor global. La elección del valor global inicial sigue pendiente.

| Valor | Escala | Half-up | Half-even |
| ---: | ---: | ---: | ---: |
| 2.25 | 1 | 2.3 | 2.2 |
| 2.35 | 1 | 2.4 | 2.4 |
| 2.45 | 1 | 2.5 | 2.4 |
| 2.55 | 1 | 2.6 | 2.6 |
| 2.245 | 2 | 2.25 | 2.24 |

El backend calcula y valida. El frontend muestra una vista previa con la misma regla y presenta el método aplicado, pero nunca es la fuente del total fiscal. Toda implementación en otro lenguaje debe superar el mismo conjunto de casos de empate exacto.

## Brechas verificadas en el repositorio

- `products.price`, `products.sale_price`, `products.purchase_price` y `order_items.unit_price` son de 2 decimales; los casts de `Product` también fijan escala 2.
- `order_items.quantity` es `DECIMAL(12,3)` solo por la mejora temporal del POS. `inventory_balances`, stocks por sucursal/almacén, movimientos, reservas, documentos de inventario, traslados y cantidades despachadas/devueltas siguen siendo enteros. Sus servicios, comandos y casts usan `int`.
- `orders` y `order_items` guardan importes a 2 decimales, pero algunas columnas tienen precisión total 10 y otras 14. Los costos del catálogo e inventario usan en varios lugares escala 4; la política debe distinguir costo unitario de valoración histórica y ampliar donde corresponda.
- El POS acepta 3 decimales para servicios y 2 para precio, agrupa líneas repetidas por producto antes de calcular el subtotal y convierte entradas a `float`. La vista previa Livewire suma productos sin redondear cada línea. El checkout web y otros flujos también usan `round()` sobre `float`.
- La generación XML propia formatea cantidad y precio a 2 decimales; el proveedor Greenter ya contempla precio unitario a 6, pero convierte a `float`. Esto requiere pruebas de consistencia con el documento emitido.
- No existe un ajuste global o de sucursal para elegir `half_up`/`half_even`, ni un registro del método usado en cada documento.

## Alcance de la siguiente mejora de backend

1. Inventariar columnas, casts, validaciones, conversiones a `int`/`float`, cálculos, serializadores y contratos externos de Ventas, Compras, Catálogo/Inventario, Facturación y Contabilidad. Confirmar límites de magnitud antes de fijar `DECIMAL(18,s)` en cada tabla.
2. Introducir un servicio decimal común que reciba y devuelva cadenas canónicas, multiplique exactamente y redondee por línea según el método efectivo. Definir el reparto determinista de céntimos residuales de descuentos e impuestos para cumplir `sum(líneas) = documento = cobro = asiento`.
3. Migrar juntos los campos y contratos de cantidad del ledger, saldos, reservas, despachos, devoluciones, traslados y espejos legacy. Conservar las restricciones de no negatividad y los triggers de integridad; no habilitar fracciones físicas hasta que todo el recorrido sea decimal.
4. Migrar precios y costos unitarios a escala 6, importes monetarios a escala 2 con capacidad suficiente, y registrar la configuración global/sucursal y el método efectivo en documentos. Ajustar el XML y cualquier integración fiscal al contrato aceptado por su proveedor.
5. Actualizar POS y demás formularios a 4/6 decimales solo cuando el servidor persista y calcule con esas escalas. Probar valores límite, empates half-up/half-even, dos líneas pequeñas cuyo redondeo por línea difiera del redondeo final, concurrencia de reservas, reversos, pagos y conciliación contable.

La migración debe ser aditiva y ensayarse con una copia de datos: inspección de valores históricos, backfill/normalización controlados, conciliación de saldos y documentos, activación gradual y plan de reversión. Un rollback de esquema a enteros no debe truncar stock fraccionario ya registrado.

# Precisión decimal del ERP

La política de cálculo utiliza aritmética decimal exacta (BCMath) en ventas, inventario y asientos contables. El modo predeterminado es `half_up`; `commerce_settings.rounding_mode` permite elegir `half_up` o `half_even` por organización y `security_branches.rounding_mode` permite sobrescribirlo por sucursal. Si la sucursal no define modo, hereda el de la organización.

| Dato | Precisión | Columna de referencia |
| --- | --- | --- |
| Precio unitario y costo unitario | 6 decimales | `DECIMAL(18,6)` |
| Cantidad y saldo de inventario | 4 decimales | `DECIMAL(18,4)` |
| Subtotal de línea, descuento, impuesto, total y asiento | 2 decimales | `DECIMAL(18,2)` |
| Valoración interna de inventario | 6 decimales | `DECIMAL(18,6)` |

Cada línea redondea `cantidad × precio unitario` a dos decimales antes de calcular el impuesto. El impuesto se calcula sobre la base de esa línea ya redondeada y descontada. El total del documento suma los importes monetarios de dos decimales; los pagos toman el total exacto del documento. Los descuentos globales se reparten entre líneas en centavos mediante los mayores restos, para que la suma de los descuentos de línea coincida con el descuento del documento.

La migración `2026_10_11_010000_expand_erp_decimal_precision.php` amplía columnas existentes; no reduce ni redondea los valores actuales. Antes de ejecutarla en una base con datos, verifique una copia de seguridad y el espacio disponible para las modificaciones de tablas. Ejecute las migraciones con la misma versión de la aplicación que usa estas columnas. El `down()` de la migración se bloquea expresamente porque volver a enteros o a menos decimales perdería información; para revertirla se necesita restaurar una copia verificada.

Las pruebas automatizadas usan SQLite. Antes de promover la imagen a producción, valide la migración en una copia de la base del motor real y compare un comprobante de prueba con el XML del proveedor fiscal. El adaptador Greenter recibe los importes ya redondeados, aunque su API usa `float` en el límite de integración.

En el XML UBL de Greenter, el descuento prorrateado se informa como descuento de línea que afecta la base imponible (código `00` del catálogo 53). El envío que el POS suma después del impuesto se informa como otro cargo (`50`), fuera de la base imponible, y el total incluye ese cargo. La fecha de emisión se interpreta en la zona horaria de Perú para conservar el día indicado en el comprobante. Esta verificación local del XML no sustituye una emisión de prueba ante SUNAT/OSE.

# ADR-002 — Normalización de producto y herencia contable

## Estado

Aceptada en FASE 02.

## Contexto

`products` mezclaba identidad, precios comerciales, stock, costo promedio y una configuración contable reducida al booleano `requires_accounting_entry`. No existía tipo de producto ni una prioridad explícita producto → categoría → empresa. El mantenimiento normal podía reescribir stock y costo promedio sin un documento operativo.

## Decisión

- El tipo de producto se modela con el enum respaldado `ProductType`: `bien_fisico`, `servicio`, `suscripcion`, `digital`, `kit` e `informativo`.
- El tratamiento contable se modela con `ProductAccountingTreatment`: `HEREDAR`, `AUTOMATICO`, `MANUAL`, `NO_APLICA` y `PENDIENTE_CONFIGURACION`.
- El tratamiento efectivo toma el primer valor distinto de `HEREDAR` en producto, categoría y configuración contable de la organización. Una cadena sin valor terminal resulta en `PENDIENTE_CONFIGURACION`.
- Las cuentas se resuelven campo por campo con prioridad producto → categoría → organización. Una categoría solo participa si pertenece a la misma organización del producto.
- `SalesAccountingService` solo genera líneas automáticas para tratamiento efectivo `AUTOMATICO`. `MANUAL` o `PENDIENTE_CONFIGURACION` detienen el autoasiento completo para evitar contabilización parcial; `NO_APLICA` excluye únicamente ese producto.
- El autoasiento usa explícitamente `order.organization_id` para settings, períodos, cuentas, cabecera y líneas; no depende del usuario o tenant implícito del worker.
- El stock y el costo promedio dejan de aceptarse en los Form Requests y de editarse desde el formulario normal. Sus columnas y lecturas permanecen para compatibilidad hasta FASE 03.

## Compatibilidad

- No se elimina ninguna columna existente.
- Los productos existentes se convierten desde `requires_accounting_entry`: verdadero → `AUTOMATICO`; falso → `NO_APLICA`.
- Los productos nuevos usan `HEREDAR`; el booleano legacy permanece como espejo para consumidores antiguos, pero la lógica nueva decide con el enum.
- Si no existe código heredado de cuenta, `SalesAccountingService` conserva temporalmente sus cuentas marcadas como default y sus fallbacks por tipo. Si existe un código explícito inválido, no cae silenciosamente a otra cuenta.
- Las migraciones tienen `down()` aditivo: retirar FASE 02 conserva las columnas y datos legacy.

## Consecuencias

- `Modules/Accounting` depende de los enums y entidades de `Modules/Catalog`; Catalog no depende de Accounting.
- Categorías y configuración contable empresarial adquieren campos de política contable.
- `tracksInventory()` expresa la semántica del tipo, pero no modifica todavía checkout, reservas, saldos ni movimientos.
- La bandeja persistente de revisión y los eventos económicos quedan para FASE 08; en FASE 02 el estado pendiente evita autoasientos inseguros.

## Alternativas descartadas

- Reutilizar solo `requires_accounting_entry`: no representa herencia, manual ni pendiente.
- Guardar la política en JSON de organización: pierde validación, consultas y contrato tipado.
- Mover el resolvedor a Catalog: introduciría una dependencia Catalog → Accounting.
- Eliminar inmediatamente `stock`, `average_price` y el booleano legacy: rompería flujos que serán migrados en fases posteriores.

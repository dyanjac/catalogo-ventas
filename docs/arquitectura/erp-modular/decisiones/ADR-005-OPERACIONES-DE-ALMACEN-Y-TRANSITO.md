# ADR-005 — Operaciones de almacén y stock en tránsito

- Estado: aceptado e implementado en FASE 05
- Fecha: 2026-07-16

## Contexto

FASE 03 estableció el ledger físico y FASE 04 separó stock reservado de stock disponible. Las transferencias heredadas descontaban e ingresaban en una sola operación, por lo que no podían representar mercadería en tránsito, recepciones parciales ni reintentos seguros. Los documentos de inventario tampoco consumían reservas ni ofrecían reversos compensatorios.

## Decisión

- `inventory_documents` sigue siendo el agregado para ingresos, salidas, aperturas, ajustes, despachos, recepciones y devoluciones locales. No se crea un segundo ledger físico.
- Un despacho asociado a una reserva consume toda la reserva y registra la salida física en la misma transacción.
- Las transferencias usan los estados `draft`, `in_transit`, `partially_received`, `received` y `cancelled`. El estado histórico `completed` se conserva solo para compatibilidad.
- Despachar descuenta el almacén origen y suma `in_transit_stock` en el saldo del almacén destino. Los espejos legacy representan únicamente stock físico.
- Cada recepción suma stock físico al destino y resta exactamente la misma cantidad de tránsito. Puede ser parcial, pero nunca superar lo despachado pendiente.
- Los eventos de transferencia son append-only, idempotentes por organización y contienen el estado anterior, estado nuevo, cantidades y movimiento físico relacionado.
- Los documentos confirmados, sus líneas y los eventos de transferencia son inmutables. Una corrección física se expresa mediante un documento y movimientos compensatorios; el original no se reescribe.
- Solo una transferencia en borrador puede cancelarse. Después del despacho, las correcciones requieren operaciones explícitas y auditables.
- Despachos, recepciones, devoluciones y reversos de FASE 05 requieren rollout ledger `active`; no se permite el downgrade mientras exista tránsito o una transferencia abierta.
- Crear un borrador, despacharlo físicamente y recibirlo son permisos RBAC separados.
- Los vínculos entre cabeceras, líneas, sucursales, almacenes, productos, saldos y movimientos se validan también mediante triggers tenant en SQL.
- Lotes, series y FEFO quedan fuera hasta contar con una decisión específica.

## Orden de bloqueos

1. Cabecera del agregado (`inventory_transfers`, `inventory_documents` o `inventory_reservations`).
2. Reserva, cuando el despacho la consume.
3. Líneas de transferencia por ID ascendente con lectura bloqueante actual.
4. Espejos legacy de sucursal y almacén por ID ascendente.
5. `inventory_balances` por ID ascendente.
6. Movimientos, eventos y actualización del estado agregado.

Una lectura no bloqueante previa puede iniciar un snapshot antiguo en MySQL `REPEATABLE READ`. Por ello las cantidades acumulables de recepción siempre se releen con `FOR UPDATE` después de bloquear la cabecera.

## Matriz de estados de transferencia

| Estado actual | Operación | Estado siguiente | Efecto de stock |
|---|---|---|---|
| `draft` | despachar | `in_transit` | origen físico `-q`; destino tránsito `+q` |
| `draft` | cancelar | `cancelled` | ninguno |
| `in_transit` | recibir parcial | `partially_received` | destino físico `+q`; destino tránsito `-q` |
| `in_transit` | recibir todo | `received` | destino físico `+q`; destino tránsito `-q` |
| `partially_received` | recibir parcial | `partially_received` | destino físico `+q`; destino tránsito `-q` |
| `partially_received` | completar | `received` | destino físico `+q`; destino tránsito `-q` |
| terminal | cualquier transición | rechazada | ninguno |

## Consecuencias

- La conservación se comprueba como físico origen + físico destino + tránsito, descontando únicamente salidas o agregando ingresos externos explícitos.
- La recepción parcial es segura frente a concurrencia y reintentos; una sobre-recepción revierte toda su transacción.
- El costo del despacho se fija al salir y se reutiliza en cada recepción parcial.
- La conciliación compara `in_transit_stock` con las cantidades despachadas aún no recibidas.
- Ventas, POS, e-commerce, comprobantes, GRE y asientos contables siguen fuera de esta fase y se integrarán desde FASE 06 en adelante.

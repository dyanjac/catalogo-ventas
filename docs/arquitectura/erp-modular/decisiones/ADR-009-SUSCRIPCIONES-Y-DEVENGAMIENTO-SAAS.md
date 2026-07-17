# ADR-009: Suscripciones comerciales y devengamiento SaaS

## Estado

Aceptada el 2026-07-17.

## Contexto

`organization_plan_subscriptions` representa la habilitaciÃ³n de capacidades del ERP para un tenant. No contiene cliente comercial, producto, precio, moneda, ciclo, periodo de servicio ni calendario contable. El catÃ¡logo sÃ­ distingue productos `suscripcion`, pero hasta FASE 09 una venta anticipada reconocÃ­a el ingreso completo al emitirse y no existÃ­a un contrato recurrente.

## DecisiÃ³n

Se crea `Modules/Subscriptions` como dominio comercial separado de los entitlements de plataforma.

- El contrato raÃ­z es `customer_subscriptions` y pertenece a una organizaciÃ³n emisora.
- Cada renovaciÃ³n crea un `subscription_service_period`; ningÃºn periodo histÃ³rico se extiende o sobrescribe.
- Los periodos usan intervalos semiabiertos `[service_starts_on, service_ends_on)` en UTC.
- El calendario se materializa en `subscription_accrual_schedules` y los importes se guardan en unidades menores (`bigint`).
- La distribuciÃ³n es diaria entre cortes mensuales; el Ãºltimo tramo absorbe el residuo para conservar exactamente el subtotal.
- Una venta no vinculada conserva el reconocimiento ordinario. Al vincular un comprobante exclusivo a un periodo se registra `subscription_deferred`, que reclasifica ingreso a ingreso diferido usando un snapshot inmutable de cuentas. `service_accrued` libera ese mismo pasivo.
- Subscriptions nunca crea asientos directamente. Registra eventos FASE 08 idempotentes e inmutables.
- La cancelaciÃ³n inmediata se admite antes de iniciar el periodo; dentro del periodo se exige cancelaciÃ³n al cierre hasta implementar prorrateo fiscal. Los ajustes son negativos, no pueden exceder ingreso ya devengado y no mutan eventos ni asientos publicados.
- Los workers reclaman filas con lock, lease e identidad Ãºnica. `ShouldBeUnique`, `withoutOverlapping` y `onOneServer` son defensas adicionales, no la fuente de idempotencia.
- La primera versiÃ³n soporta ciclos de 1, 3 y 12 meses, facturaciÃ³n anticipada y renovaciÃ³n automÃ¡tica. No hace prorrateo automÃ¡tico ni cobro automÃ¡tico.

## Consecuencias

- Los planes `basic` y `legacy_full` existentes no se convierten en contratos facturables ni generan cargos retroactivos.
- La organizaciÃ³n debe configurar `account_deferred_revenue` por producto, categorÃ­a o empresa.
- Un calendario puede quedar `event_recorded` mientras su evento contable estÃ¡ pending/error; el retry contable conserva la evidencia y evita doble devengamiento.
- Los workers de desarrollo deben consumir `subscriptions,accounting,default`.
- La migraciÃ³n se niega a revertir si existen suscripciones comerciales.

## Alternativas descartadas

- Reutilizar `organization_plan_subscriptions`: mezcla acceso tÃ©cnico con contrato comercial y producirÃ­a facturaciÃ³n retroactiva insegura.
- Crear pedidos ficticios para cada cuota: acopla el contrato a semÃ¡ntica de venta puntual e inventario.
- Calcular el devengamiento al vuelo: pierde trazabilidad, dificulta reproceso e introduce duplicados bajo concurrencia.

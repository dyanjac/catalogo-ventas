# ADR-010: Activación histórica contable sellada

## Estado

Aceptada el 2026-07-17.

## Contexto

Los eventos económicos de FASE 08 protegen los nuevos hechos mediante identidad de fuente, clave idempotente y payload inmutable, pero no convierten automáticamente documentos anteriores en asientos. Un backfill directo es inseguro: la fuente o la configuración puede cambiar entre la revisión y la ejecución, `record()` puede autoencolar el evento y algunos productores usan la hora actual cuando falta una fecha económica.

## Decisión

La activación histórica se implementa como un agregado durable separado de eventos y asientos:

- Una corrida congela la ventana inclusiva `[cutoff_at, captured_through_at]` en UTC.
- La simulación persiste candidatos y configuración, calcula un hash SHA-256 y no crea eventos, asientos ni jobs.
- Cada candidato conserva identidad canónica FASE 08, payload, fecha económica, dependencia, preview contable, incidencias y vínculos de resultado.
- Una confirmación requiere la frase `CONFIRMAR {token}` y el hash exacto mostrado al usuario.
- Antes de confirmar y procesar se verifica que configuración, cuentas, periodos y snapshot sigan íntegros.
- El procesamiento registra eventos con auto-proceso deshabilitado y los publica por dependencia dentro de una transacción de corrida. Un error revierte los eventos y asientos nuevos del lote.
- El reproceso usa la misma corrida, fuentes, payloads y claves idempotentes; nunca borra evidencia.
- Se procesan automáticamente comprobantes emitidos, cobros totales con `paid_at`, despachos y devoluciones con costo histórico inmutable, y notas de crédito válidas.
- Ventas sin comprobante, fechas ausentes, moneda sin tipo de cambio, costos sin movimiento, documentos duplicados, relaciones cross-tenant, asientos manuales equivalentes y periodos/configuración inválidos bloquean la corrida.
- Los hechos anteriores al corte requieren saldos de apertura y no se infieren.

## Consecuencias

- Existe evidencia auditable aun para simulaciones bloqueadas.
- La configuración aplicada es la vigente y aprobada al simular; no se presenta como configuración histórica original.
- El cobro reconstruible es uno total por pedido porque todavía no existe un ledger de pagos parciales.
- La activación de suscripciones que cruzan el corte requiere un saldo inicial explícito de ingreso diferido y no se infiere automáticamente.
- La migración no puede revertirse si existen corridas de activación.

## Alternativas descartadas

- Recorrer fuentes y ejecutar `record*()` directamente: puede publicar antes de la aprobación y deja una operación parcialmente contabilizada.
- Recalcular un dry-run al confirmar: introduce una carrera entre el reporte revisado y el dataset publicado.
- Usar `created_at` o `now()` como fecha económica: inventa evidencia histórica.
- Usar costo promedio o cuentas fallback actuales: puede producir COGS o clasificación contable arbitrarios.
- Borrar asientos al reprocesar: viola la inmutabilidad y la trazabilidad contable.

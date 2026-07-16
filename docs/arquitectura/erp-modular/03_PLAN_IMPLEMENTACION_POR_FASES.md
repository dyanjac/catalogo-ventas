# Plan de implementación por fases

Cada fase termina con pruebas, revisión de migraciones/rollback y `FASE_XX_CONTEXTO_CIERRE.md`. No se avanza automáticamente.

| Código | Objetivo y alcance | Dependencias / datos | Validación, aceptación y rollback |
|---|---|---|---|
| 00 | Línea base, diagnóstico y arquitectura. Sin cambios de negocio. | Repositorio y documentación existente. | Documentos de esta carpeta; rollback no aplica. |
| 01 | Crear catálogo de planes, addons, entitlements y política de activación/desactivación. Separar RBAC de capacidades SaaS. | `organizations`, seguridad. | Pruebas de autorización por tenant; migraciones reversibles y fallback de capacidades. |
| 02 | Normalizar producto: tipos, tratamiento contable y separación de campos editables. Mantener compatibilidad. | `products`, categorías, cuentas. | Tests de herencia y compatibilidad; columnas nuevas primero, sin borrado. |
| 03 | Implementar ledger de inventario y saldo objetivo; stock inicial y ajustes formales. | Productos y saldos existentes. | Conciliación/backfill idempotente; feature flag para lectura nueva; rollback a lectura anterior. |
| 04 | Reservas, expiración, bloqueo, idempotencia, deadlocks y no-stock-negativo. | Fase 03. | Pruebas concurrentes y de reintento; liberar reservas pendientes en rollback. |
| 05 | Operaciones de almacén: despacho, recepción, ajustes, devoluciones y transferencias con tránsito. | Fases 03–04. | Matriz de estados y recepción parcial; reversos compensatorios. |
| 06 | Integrar ventas, POS y e-commerce: pedido reserva; despacho mueve stock; comprobante no lo descuenta. | Fase 05. | Tests end-to-end sin duplicación; feature flag por canal. |
| 07 | Implementar GRE remitente/transportista y vínculos con traslado. | Fase 05 y reglas SUNAT confirmadas. | Pruebas de motivos de traslado y envío simulado; no emitir en producción sin credenciales validadas. |
| 08 | Eventos económicos, reglas de contabilización, COGS, pagos, devoluciones y reversos. | Fases 02, 05 y 06. | Pruebas de balance/idempotencia; reversos, nunca borrado de asientos publicados. |
| 09 | Suscripciones y devengamiento periódico. | Modelo comercial aprobado; actualmente **NO CONFIRMADO**. | Simulación y reproceso; rollback por reverso contable. |
| 10 | Activación histórica de Contabilidad con fecha de corte, simulación y procesamiento idempotente. | Fase 08. | Reporte de inconsistencias y confirmación explícita; detener sin publicar ante errores. |
| 11 | Conciliación, observabilidad, alertas, carga y recuperación. | Todas las fases previas. | Métricas, jobs de conciliación, pruebas de recuperación y runbook. |

## Plantilla obligatoria por fase

- Objetivo, alcance y fuera de alcance.
- Módulos, tablas, archivos y migraciones afectados.
- Dependencias, riesgos, validaciones y criterios de aceptación.
- Pruebas unitarias, integración y manuales.
- Estrategia explícita de compatibilidad y rollback.
- Documento de cierre autocontenido con el prompt de continuación.

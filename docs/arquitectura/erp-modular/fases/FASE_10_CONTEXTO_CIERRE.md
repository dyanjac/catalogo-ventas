# FASE 10 — CONTEXTO DE CIERRE

## 1. Identificación

- Proyecto: ERP SaaS modular en Laravel
- Fase: 10 — Activación histórica de Contabilidad
- Fecha: 2026-07-17
- Estado: Implementada y validada en SQLite; despliegue MySQL pendiente
- Rama: `master`
- Commit inicial: `84a32bf`
- Commit final: no creado; cambios en working tree

## 2. Objetivo de la fase

Permitir seleccionar una fecha de corte, validar configuración y fuentes, simular sin publicar, reportar inconsistencias, confirmar explícitamente un snapshot y procesarlo o reprocesarlo de forma idempotente.

## 3. Alcance implementado

- `accounting_activation_runs` conserva ventana UTC, estado, hash, token, configuración, resumen, actores y resultado.
- `accounting_activation_items` conserva una fila por hecho con identidad canónica, payload, preview, dependencias, incidencias y evento/asiento resultantes.
- La simulación no escribe en `accounting_economic_events` ni `accounting_entries` y no despacha jobs.
- Fuentes automáticas: factura/boleta emitida, pago total con fecha, despacho confirmado, nota de crédito y devolución confirmada.
- Orden: factura y despacho; luego pago y nota de crédito; finalmente devolución.
- Validaciones: tenant, fecha económica, moneda contable, pedido/productos, conciliación de importes, documento original, costo histórico, cuentas explícitas activas, capability y periodo abierto.
- Detección de eventos existentes compatibles, conflictos de payload y posibles asientos manuales duplicados.
- Ventas sin comprobante quedan como `ambiguous_order_sale` y bloquean; no se infiere una venta histórica.
- Confirmación con token + hash, control de una corrida activa por tenant y rechazo por deriva de configuración o fuente.
- Procesamiento atómico de la corrida con autoencolado individual deshabilitado.
- Reproceso sobre el mismo snapshot y claves idempotentes.
- UI administrativa, permisos RBAC, job único y comandos CLI.

## 4. Contratos operativos

Simular:

```bash
php artisan accounting:history:simulate {organization_id} YYYY-MM-DD
```

Confirmar y procesar en el mismo proceso:

```bash
php artisan accounting:history:process {organization_id} {run_id} --confirmation="CONFIRMAR TOKEN" --hash="SHA256" --sync
```

Sin `--sync`, la corrida se envía a la cola `accounting`.

Después del despliegue:

```bash
php artisan migrate
php artisan db:seed --class="Modules\\Security\\Database\\Seeders\\SecurityDatabaseSeeder"
php artisan queue:work --queue=accounting,default --tries=3
```

## 5. Seguridad e idempotencia

- Todas las consultas de scan, confirmación, job y reproceso filtran `organization_id` explícitamente.
- Las identidades reutilizan las claves de FASE 08; no existe un namespace histórico alterno.
- El hash incluye ventana, configuración, estado de candidatos, payload y preview.
- Una fuente o configuración modificada obliga a crear una nueva simulación.
- La confirmación bloqueada no publica nada.
- La migración rechaza `down()` cuando existen corridas.

## 6. Evidencia de pruebas

- `HistoricalAccountingActivationTest`: 11 pruebas, 41 aserciones.
- Regresión focal de FASE 08, payloads y resolver contable: 22 pruebas, 96 aserciones aprobadas.
- Se verificaron simulación sin escrituras/jobs, fecha ausente, confirmación, orden, idempotencia, rollback integral por deriva, deriva de configuración, manifiesto retrofechado, dependencia factura/cobro, aislamiento tenant e inmutabilidad en base de datos.
- Suite global: 112 pruebas pasan, 507 aserciones, 4 pruebas MySQL opt-in omitidas y 5 fallos históricos ajenos a FASE 10 (accesos admin/paleta y `ExampleTest` sin esquema base).
- Rutas administrativas y comandos fueron descubiertos por Artisan.
- `git diff --check` sin errores al momento del cierre.

## 7. Limitaciones y pendientes controlados

- El hostname MySQL configurado (`mysql`) no resolvió desde este entorno; falta ejecutar conteos reales, migración y pruebas de concurrencia InnoDB en infraestructura disponible.
- No existe ledger de pagos parciales, múltiples cobros, reembolsos ni tipo de cambio histórico; esas fuentes no se infieren.
- Los saldos de apertura previos al corte quedan fuera de esta fase.
- Suscripciones que cruzan el corte requieren saldo inicial explícito de ingreso diferido antes de una activación automática.
- El rollback funcional mediante eventos compensatorios corresponde a FASE 11; nunca debe implementarse borrando evidencia.

## 8. Decisión registrada

Consultar `decisiones/ADR-010-ACTIVACION-HISTORICA-CONTABLE-SELLADA.md`.

## 9. Prompt para iniciar FASE 11

Lee `00_CONTEXTO_MAESTRO_PROYECTO.md`, `03_PLAN_IMPLEMENTACION_POR_FASES.md` y `fases/FASE_10_CONTEXTO_CIERRE.md`. Verifica el working tree y las migraciones reales. Ejecuta FASE 11 — Conciliación, observabilidad y cierre: conciliación kardex/saldos, documentos/movimientos y eventos/asientos; alertas, métricas, logs estructurados, herramientas administrativas, pruebas de carga y recuperación, y documentación final. No borres ni mutues evidencia contable; usa reparaciones compensatorias e idempotentes.

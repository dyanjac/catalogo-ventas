# Runbook — Operaciones, conciliación y recuperación

## Despliegue

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class="Modules\\Security\\Database\\Seeders\\SecurityDatabaseSeeder" --force
php artisan optimize:clear
php artisan operations:doctor
php artisan schedule:list
```

Configurar una cache compartida en despliegues con más de una instancia. No usar `array` ni archivos locales para `onOneServer`. El `retry_after` de la conexión de cola debe ser al menos 960 segundos.

Workers recomendados:

```bash
php artisan queue:work --queue=operations,accounting,subscriptions,billing,default --tries=3 --timeout=900
php artisan schedule:work
```

## Comprobaciones

- Liveness: `GET /up`.
- Readiness: `GET /health/ready` devuelve 200 o 503 y no contiene información del tenant.
- Panel: `/admin/operations` requiere módulo y permiso.
- Métricas JSON del tenant activo: `/admin/operations/metrics`, autenticado.
- Logs JSON: `storage/logs/operations-YYYY-MM-DD.json.log`.

## Conciliación

Encolar una organización:

```bash
php artisan operations:reconcile 12
```

Ejecutar sin cola para diagnóstico:

```bash
php artisan operations:reconcile 12 --sync
```

El modo síncrono devuelve exit code `1` si la corrida termina en `failed` o `error`; puede usarse como gate de despliegue. `degraded` conserva exit code `0`, pero exige revisar sus warnings.

Todas las organizaciones activas:

```bash
php artisan operations:reconcile --all
```

Estados: `passed`, `degraded`, `failed` o `error`. Un hallazgo crítico deja la corrida en `failed`; un warning aislado la deja en `degraded`. Reconocer un incidente no lo resuelve: solo una corrida posterior sin el hallazgo lo marca `resolved`.

## Recuperación de eventos contables abandonados

Siempre simular primero:

```bash
php artisan operations:recover-events 12 --older-than=15
```

Después de validar IDs y ausencia de un asiento ya publicado:

```bash
php artisan operations:recover-events 12 --older-than=15 --execute --confirm="RECOVER:12"
```

La herramienta solo cambia eventos `processing` antiguos, sin `processed_entry_id`, a `pending` y los reencola. No borra eventos ni asientos. Si existe un asiento o movimiento incorrecto, la corrección es un evento o movimiento compensatorio del dominio correspondiente.

## Respuesta a incidentes

1. Identificar `correlation_id`, organización, dominio, código y fuente desde el panel.
2. Verificar el registro fuente y su contraparte; no editar evidencia publicada.
3. Corregir configuración o emitir una compensación idempotente.
4. Ejecutar conciliación síncrona para confirmar.
5. Registrar la decisión en la nota de reconocimiento y conservar IDs de la compensación.

## Pruebas operativas

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array QUEUE_CONNECTION=sync \
php artisan test tests/Feature/OperationalPhaseTest.php
```

La carga es deliberadamente opt-in:

```bash
RUN_OPERATIONAL_LOAD_TESTS=1 DB_CONNECTION=sqlite DB_DATABASE=:memory: \
CACHE_STORE=array QUEUE_CONNECTION=sync \
php artisan test tests/Feature/OperationalReconciliationLoadTest.php
```

Antes de producción, ejecutar también las pruebas MySQL/InnoDB opt-in existentes y una conciliación contra una copia anonimizada del volumen real.

## Rollback

El código puede deshabilitarse retirando el módulo del despliegue y deteniendo su schedule, pero no se debe ejecutar `migrate:rollback` cuando ya existen corridas o incidentes: la migración lo rechaza para proteger evidencia. Conservar las tablas, detener jobs, corregir hacia adelante y reactivar.

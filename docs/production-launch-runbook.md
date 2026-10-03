# Lanzamiento productivo del ERP

Este runbook complementa `deploy-docker-swarm.md` y define el control final de
configuración, despliegue, observabilidad y rollback. Está preparado para un
Swarm de un nodo sin registry, con HAProxy apuntando a `127.0.0.1:8585`.

## 1. Configuración privada

Crear `.env.swarm` desde el ejemplo, mantenerlo fuera de Git y limitar sus
permisos:

```bash
cp .env.swarm.example .env.swarm
chmod 600 .env.swarm
editor .env.swarm
```

Usar una etiqueta de imagen nueva e inmutable en cada entrega. No reutilizar
`latest` ni una etiqueta ya desplegada:

```bash
ERP_IMAGE=erp-app:$(date -u +%Y%m%d%H%M%S)
```

`APP_KEY`, `DB_PASSWORD`, certificados y contraseñas nunca deben guardarse en
el repositorio. `APP_URL` debe usar HTTPS y `SESSION_SECURE_COOKIE` debe ser
`true`.

El nodo que conserva el bind mount de `storage` debe tener la etiqueta usada
por `docker-stack.yml`:

```bash
docker node update --label-add catalogo_storage=true "$(docker info --format '{{.Name}}')"
```

## 2. Preflight sin tocar datos

```bash
set -a
. ./.env.swarm
set +a

docker build --pull --tag "$ERP_IMAGE" .
docker compose --env-file .env.swarm -f docker-stack.yml config --quiet

docker run --rm --env-file .env.swarm --network "$ERP_NETWORK" \
  "$ERP_IMAGE" php artisan operations:launch-check --skip-runtime
```

El comando falla si el entorno no es `production`, el debug está activo,
falta `APP_KEY`, la URL no usa HTTPS, la cookie segura está desactivada o la
analítica opcional está incompleta. Nunca imprime secretos.

## 3. Respaldo y migración

Antes de una entrega que contenga migraciones, generar un respaldo verificable
de MySQL con el mecanismo operativo del servidor. No continuar si no se puede
restaurar el respaldo.

Ejecutar las migraciones una sola vez, antes de actualizar el servicio:

```bash
docker run --rm --env-file .env.swarm --network "$ERP_NETWORK" \
  "$ERP_IMAGE" php artisan migrate --force
```

Las migraciones de producción se corrigen hacia adelante. No ejecutar
`migrate:rollback` durante un rollback de aplicación salvo que la migración
haya sido revisada expresamente y no exista evidencia operativa dependiente.

## 4. Despliegue y verificación

```bash
docker stack deploy --resolve-image never \
  --compose-file docker-stack.yml "$ERP_STACK_NAME"

docker service ps "${ERP_STACK_NAME}_app" --no-trunc
docker service logs --tail 100 "${ERP_STACK_NAME}_app"
curl -fsS "http://127.0.0.1:${ERP_PUBLISHED_PORT}/up"
curl -fsS "http://127.0.0.1:${ERP_PUBLISHED_PORT}/health/ready"
```

`/up` es liveness. `/health/ready` comprueba base de datos, caché y esquema;
es también el healthcheck usado por Swarm para aceptar o revertir la tarea.

Ejecutar el chequeo completo dentro del contenedor activo:

```bash
APP_CONTAINER=$(docker ps \
  --filter "label=com.docker.swarm.service.name=${ERP_STACK_NAME}_app" \
  --format '{{.ID}}' | head -n 1)

test -n "$APP_CONTAINER"
docker exec "$APP_CONTAINER" php artisan operations:launch-check
```

Finalmente validar por HTTPS la portada, el registro, el acceso administrativo
y al menos una tienda tenant:

```bash
curl -fsS -o /dev/null https://erp.example.com/
curl -fsS -o /dev/null https://erp.example.com/saas/register
curl -fsS -o /dev/null https://erp.example.com/admin/login
curl -fsS -o /dev/null https://erp.example.com/ecommerce/COMERCIO-DE-PRUEBA
```

## 5. Conciliación estricta

Mantener `OPERATIONS_READY_REQUIRE_RECONCILIATION=false` durante el primer
arranque. Cuando la conciliación de todas las organizaciones termine bien:

```bash
docker exec "$APP_CONTAINER" php artisan operations:reconcile --all --sync
```

Se puede cambiar la variable a `true` en `.env.swarm` y volver a desplegar. A
partir de entonces, una conciliación ausente o vencida hace fallar readiness.

## 6. Analítica de conversión opcional

La portada integra Plausible de forma opt-in. Registra vistas y los eventos
`Registration Started` y `Registration Submitted`; solo adjunta la ubicación
del CTA y nunca datos de formularios ni identificadores del tenant.

```dotenv
MARKETING_ANALYTICS_ENABLED=true
MARKETING_ANALYTICS_DOMAIN=erp.example.com
MARKETING_ANALYTICS_SCRIPT_URL=https://plausible.io/js/script.js
```

Si no existe una instancia/proyecto de analítica aprobado, dejarla desactivada.

## 7. Rollback operativo

Swarm tiene `failure_action: rollback`, por lo que una tarea que no alcanza
readiness vuelve automáticamente a la especificación anterior. Para revertir
manualmente la imagen:

```bash
docker service rollback "${ERP_STACK_NAME}_app"
docker service ps "${ERP_STACK_NAME}_app" --no-trunc
curl -fsS "http://127.0.0.1:${ERP_PUBLISHED_PORT}/health/ready"
```

No borrar la imagen previa hasta terminar la ventana de observación. Si hubo
una migración incompatible, restaurar la base requiere el procedimiento de
respaldo aprobado; el rollback del servicio no revierte el esquema.

## 8. Criterios de cierre

- Servicio `1/1` y sin tareas reiniciando.
- Liveness y readiness en HTTP 200.
- `operations:launch-check` exitoso.
- Flujo `/` → `/saas/register` → `/admin/login` comprobado por HTTPS.
- Una ruta `/ecommerce/{nombre-comercio}` comprobada.
- Logs sin errores nuevos y rollback de imagen conocido por el operador.

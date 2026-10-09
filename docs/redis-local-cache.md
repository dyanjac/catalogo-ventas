# Redis local para la caché de navegación

El entorno Compose incluye Redis para la caché compartida entre peticiones. El
servicio solo está disponible para los contenedores de la red de Docker; no
publica el puerto 6379 en el host.

## Credenciales locales

Las credenciales reales se guardan en `.env.docker.local`, que está ignorado por
Git. Para preparar una máquina nueva, copia el archivo base y define una
contraseña robusta para `REDIS_PASSWORD`:

```sh
cp .env.docker .env.docker.local
chmod 600 .env.docker.local
```

Define también `REDIS_USERNAME=erp_cache`, `REDIS_DB=0`,
`REDIS_CACHE_DB=1`, `CACHE_STORE=redis` y
`NAVIGATION_SHARED_CACHE_ENABLED=true` en ese archivo local.

## Inicio de la instancia aislada

```sh
ERP_ENV_FILE=.env.docker.local \
ERP_APP_IMAGE=erp-app:feature-respuesta-navegacion \
docker compose up -d --no-build
```

La aplicación queda disponible en `http://127.0.0.1:8080`. Sus datos usan el
volumen local `catalogo-ventas_mysql_data`; no comparten la base de datos ni el
servicio de Swarm publicado.

## Verificación y acceso a Redis

```sh
docker compose ps
docker exec -it erp-redis sh
redis-cli --user "$REDIS_USERNAME" --pass "$REDIS_PASSWORD" --no-auth-warning ping
```

La respuesta esperada es `PONG`. Para detener solo esta instancia local:

```sh
ERP_ENV_FILE=.env.docker.local docker compose down
```

Redis se usa exclusivamente como caché en esta fase: no persiste datos al
reiniciar. Para desactivar la caché compartida sin retirar Redis, define
`NAVIGATION_SHARED_CACHE_ENABLED=false` y reinicia la aplicación.

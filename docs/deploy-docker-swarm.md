# Despliegue del ERP con Docker Swarm

Este procedimiento despliega **solo la aplicación ERP**. No crea ni modifica
MySQL, phpMyAdmin ni ningún otro servicio. La imagen se construye en el mismo
VPS donde se clonó el repositorio y el stack ejecuta una réplica de esa imagen.

`docker-compose.yml` se mantiene como entorno local: inicia `app`, `mysql` y
`phpmyadmin`. No se debe usar para producción ni ejecutar `docker compose down`
si ese MySQL existente contiene datos que se desean conservar.

## Hallazgos de la configuración

- `Dockerfile` ya compila dependencias PHP y los assets Vite en etapas
  separadas y sirve Laravel con Apache en el puerto 80.
- El contenedor crea `public/storage` al iniciar para que las cargas sean
  accesibles. El ERP configura `wkhtmltopdf` para PDFs, pero ese binario no
  viene en la imagen actual; debe incorporarse y validarse antes de habilitar
  esa funcionalidad en producción.
- `.env.docker` se excluye del contexto de construcción. Así sus credenciales
  no quedan dentro de la imagen; úsalo únicamente para el compose local. Como
  el archivo está versionado actualmente, no debe contener credenciales reales
  de producción y cualquier valor sensible histórico debe rotarse.
- Los documentos, certificados, XML, PDFs e imágenes que genera el ERP se
  guardan en `storage`. Por eso `docker-stack.yml` monta una ruta persistente
  del VPS y fija una sola réplica en ese nodo. No aumentes las réplicas sin
  cambiar a almacenamiento compartido (S3/NFS/volumen distribuido).
- La migración no se ejecuta al inicio del servicio: hacerlo al reiniciar una
  tarea puede introducir cambios no deseados. Se ejecuta una vez y de forma
  explícita durante cada despliegue.

## 1. Preparar el VPS

Instala Docker Engine y el complemento `docker compose`, inicia sesión con un
usuario con permisos sobre Docker y clona el repositorio:

```bash
git clone <URL_DEL_REPOSITORIO> /srv/erp/catalogo-ventas
cd /srv/erp/catalogo-ventas
docker version
docker compose version
```

Inicializa Swarm solo si el VPS todavía no es un manager. Para un VPS de un
solo nodo basta:

```bash
docker info --format '{{.Swarm.LocalNodeState}}'
docker swarm init
```

Si el servidor ya pertenece a un clúster, ejecuta los comandos `docker stack`
en un nodo manager. En un clúster de varios nodos, abre entre nodos los puertos
TCP 2377, TCP/UDP 7946 y UDP 4789.

## 2. Conectar con el MySQL que ya existe

El servicio `app` usa una red externa llamada por defecto `erp_backend`; el
stack no crea MySQL. El valor correcto de `DB_HOST` depende de dónde esté esa
base de datos:

| Ubicación de MySQL | `DB_HOST` | Red requerida |
| --- | --- | --- |
| Servicio Swarm existente | Nombre del servicio, por ejemplo `mysql` | El servicio MySQL y ERP deben compartir la misma red overlay. |
| MySQL fuera de Docker o en otro servidor | IP privada o DNS del servidor MySQL | La red debe permitir TCP 3306 desde el VPS. |
| Contenedor Compose/standalone en el mismo VPS | IP privada del VPS o DNS accesible, nunca `127.0.0.1` | MySQL debe publicar 3306 de manera restringida al VPS, o migrarse a una red overlay compartida. |

Si MySQL es un servicio Swarm, identifica primero su red y utiliza ese nombre
en `ERP_NETWORK`. Si todavía no existe una red compartida, créala una sola vez
en un manager y conecta el servicio MySQL existente a ella:

```bash
docker network create --driver overlay --attachable erp_backend
docker service update --network-add erp_backend <STACK>_<SERVICIO_MYSQL>
```

No crees esta red ni modifiques el servicio MySQL si ya tiene una red overlay
adecuada: simplemente usa su nombre en `ERP_NETWORK`.

Si MySQL está fuera de Swarm, el ERP igualmente requiere su propia red overlay
externa porque así está definido el stack. Créala una sola vez (no conecta ni
altera MySQL):

```bash
docker network create --driver overlay --attachable erp_backend
```

## 3. Crear la configuración privada

El archivo `.env.swarm` contiene credenciales y no se versiona. Crea la copia
en el VPS y restríngele los permisos:

```bash
cp .env.swarm.example .env.swarm
chmod 600 .env.swarm
hostname
editor .env.swarm
```

Completa como mínimo estos valores:

- `ERP_NODE_HOSTNAME`: resultado exacto de `hostname` del VPS que ejecutará la
  aplicación.
- `ERP_STORAGE_PATH`: ruta absoluta persistente, por ejemplo `/srv/erp/storage`.
- `ERP_NETWORK`: red overlay que comparte con MySQL, si MySQL es un servicio
  Swarm.
- `APP_URL`: URL pública final, con `https://` si habrá proxy TLS.
- `APP_KEY`: genera una clave nueva solo en el primer despliegue y luego
  consérvala; cambiarla invalida sesiones y datos cifrados.
- Credenciales reales de la base de datos en `DB_*`. El usuario debe tener
  permisos para las tablas y migraciones del ERP.

Para generar `APP_KEY` sin copiarla a la imagen, primero construye la imagen
del paso siguiente y ejecuta:

```bash
docker run --rm --entrypoint php erp-app:2026.09.13 artisan key:generate --show
```

Pega el resultado completo (`base64:...`) como valor de `APP_KEY`.

## 4. Preservar archivos y liberar el puerto (si vienes de Compose)

El compose actual publica la aplicación en el puerto 8080. Si está activo y
vas a reutilizar ese puerto, guarda antes sus archivos cargados y detén solo
la aplicación. No detengas MySQL con `docker compose down`.

```bash
set -a
. ./.env.swarm
set +a
mkdir -p "$ERP_STORAGE_PATH"
docker cp erp-app:/var/www/html/storage/. "$ERP_STORAGE_PATH"/
docker compose stop app
docker compose rm -f app
```

Si es la primera instalación, basta crear la carpeta:

```bash
set -a
. ./.env.swarm
set +a
mkdir -p "$ERP_STORAGE_PATH"
```

Omite `docker cp` si el contenedor local no existe. Copia manualmente al mismo
directorio cualquier certificado de facturación, por ejemplo en
`$ERP_STORAGE_PATH/app/private/transport/certificates`, antes de usar esa
función.

## 5. Construir, migrar y desplegar

`docker stack deploy` ignora `build`; por eso la imagen se construye primero.
En un Swarm de un VPS la imagen local es suficiente. En un clúster de varios
nodos hay que publicarla en un registro accesible para todos los nodos.

```bash
set -a
. ./.env.swarm
set +a

docker build --pull --tag "$ERP_IMAGE" .

# Revisa que las variables se interpolen sin mostrar la clave ni contraseña.
docker compose --env-file .env.swarm -f docker-stack.yml config --quiet

# Solo después de verificar la conexión a MySQL. Repetible en actualizaciones.
docker run --rm --env-file .env.swarm --network "$ERP_NETWORK" \
  "$ERP_IMAGE" php artisan migrate --force

docker stack deploy --resolve-image never --compose-file docker-stack.yml erp
```

La red debe ser `attachable` para el comando temporal de migración. Si el
servicio MySQL está en una red overlay no attachable, ejecuta la migración como
una tarea temporal de Swarm o habilita una red overlay compartida y attachable;
no cambies `RUN_MIGRATIONS` a `true` como sustituto.

## 6. Verificar y operar

```bash
docker stack services erp
docker service ps erp_app --no-trunc
docker service logs -f erp_app
curl -fsS "http://127.0.0.1:${ERP_PUBLISHED_PORT}/up"
```

El endpoint `/up` confirma que Laravel responde. Después configura el proxy
inverso/TLS existente para reenviar el dominio a `127.0.0.1:8080` (o al valor
de `ERP_PUBLISHED_PORT`) y deja abierto públicamente solo 80/443. No expongas
MySQL al público.

Para actualizar, cambia `ERP_IMAGE` a una etiqueta nueva, vuelve a construir,
ejecuta la migración y despliega de nuevo. El stack usa actualización de una
tarea y rollback automático si la nueva tarea no se mantiene sana:

```bash
set -a
. ./.env.swarm
set +a
docker build --pull --tag "$ERP_IMAGE" .
docker run --rm --env-file .env.swarm --network "$ERP_NETWORK" \
  "$ERP_IMAGE" php artisan migrate --force
docker stack deploy --resolve-image never --compose-file docker-stack.yml erp
```

Para volver temporalmente a una imagen previa, fija `ERP_IMAGE` a su etiqueta
anterior y vuelve a desplegar. Una migración de base de datos no siempre puede
revertirse automáticamente: toma una copia de seguridad de MySQL antes de cada
actualización que incluya migraciones.

## Límites del stack

`docker-stack.yml` no gestiona MySQL, phpMyAdmin, RabbitMQ, Redis, correo ni
el proxy TLS. Si se habilitan colas asíncronas (`QUEUE_CONNECTION` distinto de
`sync`) se deberá desplegar además un servicio worker explícito, conectado a
la misma infraestructura, en vez de asumir que Apache procesará la cola.

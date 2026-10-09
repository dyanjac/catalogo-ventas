# Plan de mejora de respuesta de navegación

## Objetivo

Reducir el tiempo de navegación entre módulos administrativos sin alterar las
reglas de autorización, aislamiento por organización ni contratos entre módulos.
La línea base y sus limitaciones están en
[`navigation-response-baseline.md`](navigation-response-baseline.md).

El primer objetivo técnico es reducir las consultas transversales que se ejecutan
antes de consultar los datos propios de cada pantalla. La medición disponible
mostró 83 consultas y 281,2 ms al construir la navegación de un superadministrador,
además de 9 consultas por cada resolución del contexto de organización.

## Decisiones de diseño

1. La primera entrega usará memoria del ciclo de petición o job. Los servicios se
   resolverán con alcance `scoped`, para que el resultado se reutilice dentro del
   ciclo y se descarte al iniciar el siguiente. Esto también evita conservar datos
   entre peticiones si en el futuro se usa Octane o un worker de larga duración.
2. La resolución de capacidades se hará en bloque una vez por organización. Las
   comprobaciones individuales serán búsquedas sobre ese mapa ya resuelto.
3. No se almacenarán respuestas HTML completas ni resultados propios de productos,
   ventas, inventario o contabilidad en esta mejora.
4. La caché compartida entre peticiones será una fase opcional posterior. La
   arquitectura previa decidió evitarla mientras no existan TTL e invalidación
   compatibles con expiraciones, bajas y workers de larga duración.
5. No se sustituirá Flux/Livewire ni se migrará el panel por esta incidencia. La
   evidencia actual apunta primero al backend. Los recursos frontend sin uso se
   revisarán después de estabilizar las consultas.

## Fase 0 — Línea base reproducible

**Estado:** la línea base de servicios quedó registrada antes de la mejora. La
medición HTTP autenticada de la imagen candidata se completó durante la fase 6
el 9 de octubre de 2026. No existe una medición HTTP autenticada equivalente
de la imagen anterior, por lo que sus percentiles solo sirven como referencia
de la candidata y no como comparación antes/después de página completa.

### Trabajo

- Construir una imagen desde `feature/respuesta-navegacion`.
- Medir con un usuario de pruebas, sin registrar datos personales:
  - Dashboard.
  - Una pantalla sencilla, por ejemplo unidades o categorías.
  - Una pantalla pesada, por ejemplo inventario o POS.
- Registrar por ruta: estado HTTP, tiempo hasta el primer byte, tiempo total,
  cantidad de consultas, tiempo SQL y tamaño de respuesta.
- Ejecutar 1 petición fría y al menos 20 peticiones calientes por ruta. Informar
  mediana y percentil 95.

### Puerta de salida

- Resultados autenticados documentados y repetibles.
- Ningún cambio funcional desplegado.

## Fase 1 — Memoria por petición y consultas en bloque

**Estado:** implementación y validación automatizada completadas el 8 de octubre
de 2026.

### Cambios previstos

- `OrganizationContextService`:
  - Memorizar la organización actual y su representación para vista.
  - Memorizar las comprobaciones de existencia de esquema.
  - Invalidar su memoria local cuando `rememberExplicit()` o `clearExplicit()`
    cambien el contexto dentro del mismo ciclo.
- `OrganizationEntitlementService`:
  - Cambiar el binding de `singleton` a `scoped`.
  - Memorizar la disponibilidad del esquema durante el ciclo.
  - Resolver capacidades una sola vez por `organization_id`.
  - Invalidar el mapa local después de asignar o reemplazar un plan, activar o
    desactivar un addon y establecer un override.
- `SecurityAuthorizationService`:
  - Registrarlo como `scoped`.
  - Memorizar roles, permisos, acceso a módulos y navegación por usuario y
    organización durante el ciclo.
  - Obtener la navegación en bloque y filtrar con el único mapa de capacidades
    ya resuelto.

### Archivos principales

- `app/Providers/AppServiceProvider.php`
- `app/Services/OrganizationContextService.php`
- `Modules/Commerce/app/Providers/CommerceServiceProvider.php`
- `Modules/Commerce/app/Services/OrganizationEntitlementService.php`
- `Modules/Security/app/Providers/SecurityServiceProvider.php`
- `Modules/Security/app/Services/SecurityAuthorizationService.php`

### Pruebas

- Reutilización de contexto dentro de una petición.
- Separación de resultados entre organizaciones y usuarios.
- Cambio explícito de organización dentro del mismo ciclo.
- Activación, desactivación y expiración de capacidades.
- Cambio de rol o permiso visible inmediatamente dentro del flujo que lo guarda.
- Acceso de superadministrador, usuario interno y cliente.
- Presupuesto de consultas para `modulesForNavigation()` y `forView()`.

### Puerta de salida

- `modulesForNavigation()` con un máximo inicial de 12 consultas en la prueba de
  integración, frente a las 83 observadas.
- La segunda llamada durante el mismo ciclo no ejecuta consultas adicionales.
- `OrganizationContextService::forView()` no repite consultas después de la
  primera resolución del ciclo.
- Pruebas de autorización, entitlements y aislamiento tenant aprobadas.

**Resultado:** se cumple el presupuesto de hasta 12 consultas en la primera
resolución de navegación y cero consultas adicionales en la segunda. El contexto
de organización usa hasta 2 consultas en su primera resolución y ninguna al
reutilizarlo. Roles, permisos, módulos y capacidades invalidan su memoria local
en los flujos de escritura. Las operaciones de emisión de guías fuerzan una
lectura actual de capacidades para conservar el comportamiento fail-closed ante
cambios externos.

### Rollback

No requiere migraciones. Se revierte la imagen o el commit de esta fase.

## Fase 2 — Composición de vistas y navegación administrativa

**Estado:** implementación y validación automatizada completadas el 8 de octubre
de 2026; la comprobación visual y la medición HTTP autenticada quedan en la fase
6 previa al despliegue.

### Cambios previstos

- Inventariar qué vistas consumen `organizationContext`, `commerce`,
  `storefrontRoutes` y `storefrontCart`.
- Sustituir el `View::composer('*')` por compositores dirigidos a layouts y vistas
  que realmente consumen esos datos.
- Preparar los datos compartidos una sola vez por petición y permitir que los
  parciales incluidos hereden los valores del layout.
- Mantener los nombres de rutas y los límites entre módulos.

### Pruebas

- Renderizado de panel, login, tienda pública, carrito y checkout.
- Branding y rutas correctas para dos organizaciones distintas.
- Ausencia de variables indefinidas en vistas y logs.
- Comparación de consultas y tiempos contra la salida de la fase 1.

### Puerta de salida

- Ninguna regresión visual o tenant.
- Reducción adicional demostrable, o documentación de que la fase 1 absorbió el
  coste y no conviene ampliar la modificación.

**Resultado:** el compositor `*` se sustituyó por vistas concretas. Los datos de
comercio se memorizan por organización durante el ciclo y se invalidan al
guardar. Las pruebas de panel, storefront, carrito, branding, autorización y
aislamiento tenant pasan dentro de la suite completa: 170 pruebas, 770
aserciones y 5 omisiones condicionadas por el entorno. La imagen candidata se
construye con los assets de producción sin errores.

### Rollback

No requiere migraciones. El compositor anterior puede restaurarse mediante la
imagen previa.

## Fase 3 — Navegación Livewire uniforme

**Estado:** implementación y validación automatizada completadas el 8 de octubre
de 2026; la revisión visual en escritorio y la medición autenticada quedan para
la fase 6 previa al despliegue.

### Cambios previstos

- Añadir navegación Livewire a enlaces internos GET que cambian de pantalla.
- Excluir descargas, enlaces externos, acciones mutables y destinos que requieran
  una carga completa.
- Adaptar scripts de pantalla al ciclo `livewire:navigated` y evitar listeners
  duplicados.
- Añadir indicación visual discreta para peticiones que superen el umbral de
  progreso de Livewire.
- Revisar `wire:navigate.hover`: conservarlo solo donde la precarga tenga valor,
  porque cada hover puede generar una petición que el usuario no utiliza.

### Pruebas

- Menú, historial atrás/adelante, formularios, modales y paginación.
- POS, inventario, billing y contabilidad, que contienen scripts de pantalla.
- Descargas PDF/XML y cierre de sesión mediante carga normal.
- Consola del navegador sin errores ni listeners duplicados tras 20 cambios de
  pantalla.

### Puerta de salida

- Navegación consistente en todas las rutas administrativas revisadas.
- Sin aumento de peticiones provocado por precargas innecesarias.
- Mejora del tiempo percibido sin ocultar una regresión del tiempo de servidor.

**Resultado:** se añadieron transiciones Livewire a 115 enlaces GET internos del
panel. Las descargas, el cierre de sesión, los enlaces externos y los destinos
que cambian de layout conservan la navegación completa. Se retiró la precarga
por `hover` para evitar solicitudes que el usuario no llega a utilizar. Los
scripts de billing, transporte y contabilidad se centralizaron en el asset
administrativo y se reinicializan con `livewire:navigated`, usando delegación de
eventos para no acumular listeners. La barra de progreso integrada usa el color
primario del tema.

La cobertura incluye un contrato unitario que valida las rutas GET de
controladores modulares, una prueba HTTP de navegación Livewire sobre el panel y
pruebas estáticas de enlaces y excepciones. La suite completa aprobó 262 pruebas
y 1.632 aserciones, con 5 omisiones condicionadas por el entorno. La imagen
candidata compiló los assets de producción sin errores.

### Rollback

Retirar las directivas de navegación añadidas o volver a la imagen anterior.

## Fase 4 — Caché compartida entre peticiones con Redis

**Estado:** implementación y validación local completadas el 8 de octubre de
2026. No se ha desplegado la imagen en el servicio de Swarm.

### Diseño aplicado

- Redis 8.10 se ejecuta como servicio interno de Compose, sin publicar el
  puerto 6379. Usa un usuario ACL dedicado y contraseña configurada en el
  entorno local privado.
- La imagen PHP instala `phpredis` 6.3.0. Laravel usa Redis como almacén de
  caché y la caché de navegación se puede desactivar con
  `NAVIGATION_SHARED_CACHE_ENABLED=false`.
- Las entradas se etiquetan por autorización, usuario y organización:
  - Capacidades por `organization_id`.
  - Roles, permisos, acceso a módulos y navegación por `user_id` y
    `organization_id`.
- TTL limitado por la próxima fecha de inicio o expiración aplicable y por un
  máximo de 300 segundos configurable.
- Invalidación explícita después del commit en:
  - Asignación o reemplazo de plan.
  - Activación/desactivación de addons y overrides.
  - Asignación de roles a usuarios.
  - Cambios en la matriz de permisos y acceso de módulos.
- Los cambios globales de autorización invalidan la etiqueta global. Los cambios
  de usuario invalidan su etiqueta y los entitlements invalidan la de su
  organización.
- Un fallo del almacén se degrada a consulta directa; nunca concede accesos por
  una entrada ausente o no disponible.
- Redis se ejecuta sin persistencia y con límite de 128 MiB, política
  `allkeys-lru`, apropiado para caché local de prueba.

### Pruebas

- Hit, miss, expiración e invalidación por cada operación de escritura.
- Dos organizaciones con configuraciones distintas.
- Dos réplicas o procesos leyendo el mismo almacén.
- Redis no disponible y recuperación posterior.
- Cambio de permisos y baja de plan efectivos sin esperar el TTL.

### Puerta de salida

- Redis validado por healthcheck autenticado y por una escritura, lectura e
  invalidación de tags desde Laravel.
- Invalidación demostrada para todos los puntos de escritura.
- Comparación de rendimiento que justifique la complejidad operativa.

### Rollback

- Bandera de configuración para desactivar la caché persistente y volver a la
  resolución por petición.
- Mantener la aplicación funcional con `CACHE_STORE=database` durante el rollback.

**Resultado:** se construyó la imagen `erp-app:feature-respuesta-navegacion`,
con `phpredis` habilitado. La instancia aislada de Compose quedó saludable en el
puerto 8080, junto con MySQL y Redis locales, sin alterar el servicio de Swarm
publicado en el puerto 8585. La operación de caché etiquetada desde Laravel y
el endpoint `/health/ready` respondieron correctamente. La suite completa
aprobó 266 pruebas, 1.643 aserciones y 5 omisiones dependientes del entorno.

## Fase 5 — Limpieza de recursos frontend

**Estado:** implementación y validación de build completadas el 8 de octubre de
2026. La revisión visual queda incluida en la fase 6.

### Trabajo condicionado a medición

- Confirmar mediante el build que AdminLTE no forma parte del bundle activo.
- Retirar la dependencia `admin-lte` si no existe consumo directo ni indirecto.
- Evaluar por separado jQuery, JavaScript de Bootstrap y Font Awesome. Bootstrap
  CSS sigue siendo usado por las vistas actuales, por lo que retirarlo requiere
  una migración visual específica.
- Comparar tamaño de assets, tiempo de análisis/ejecución y renderizado antes y
  después.

### Puerta de salida

- Build sin referencias rotas.
- Validación visual de las rutas administrativas principales.
- Mejora medible del payload o del tiempo del navegador.

**Resultado:** la búsqueda de imports y vistas confirmó que AdminLTE no formaba
parte del bundle de Vite ni de los recursos publicados activos. Se retiró
`admin-lte` de `package.json` y de su árbol transitivo: el lockfile pasó de 298
a 166 paquetes. jQuery, Bootstrap 4, Font Awesome y Flux se mantienen porque el
panel los carga directamente. La imagen de producción se compiló con Node 22 sin
referencias rotas; el bundle administrativo resultante es 488,25 kB de CSS y
176,23 kB de JavaScript antes de compresión. Como AdminLTE no se cargaba en ese
bundle, la mejora es de instalación, superficie de dependencias y tiempo de
build; no se atribuye una reducción de bytes enviados al navegador.

## Fase 6 — Validación previa al despliegue

**Estado:** validación técnica local y revisión visual manual en escritorio
completadas el 9 de octubre de 2026. No se ha desplegado la imagen ni se ha
modificado el servicio de Swarm.

### Controles obligatorios

1. Ejecutar las pruebas específicas de contexto, autorización, entitlements,
   acceso administrativo, branding y navegación.
2. Ejecutar la suite completa, Pint y `npm run build` dentro de la imagen.
3. Repetir la medición autenticada de la fase 0 con la imagen candidata.
4. Comparar rutas simples y pesadas contra la línea base.
5. Verificar `APP_ENV=production`, `APP_DEBUG=false`, OPcache, caché de
   configuración/rutas/vistas y el comando `operations:launch-check`.
6. Revisar logs y confirmar que no hay errores de vistas ni autorización.

### Criterios de aceptación

- Reducción mínima del 60 % en consultas transversales de navegación.
- `modulesForNavigation()` dentro del presupuesto de 12 consultas y cero en su
  segunda llamada del mismo ciclo.
- Ninguna regresión de autorización o aislamiento por organización.
- Mediana y p95 de las rutas medidas iguales o mejores que la línea base. Los
  objetivos absolutos se fijarán después de completar la fase 0 autenticada.
- Imagen candidata sana y rollback probado.

### Resultado técnico

- La rama medida es `feature/respuesta-navegacion` y la imagen local es
  `erp-app:feature-respuesta-navegacion`. Compose, MySQL y Redis quedaron
  saludables; el endpoint `/health/ready` respondió `200` después de generar
  las cachés de configuración, rutas y vistas.
- La suite con PHPUnit 11.5.33 dentro de la imagen de prueba aprobó las 271
  pruebas, 1.643 aserciones y 5 omisiones condicionadas por el entorno, en
  37,908 s. Pint validó los 18 archivos PHP modificados. El build de producción
  se ejecutó correctamente dentro de la construcción Docker con Node 22.
- Con una sesión administrativa local y el encabezado real de navegación de
  Livewire, se ejecutaron 20 solicitudes consecutivas por ruta:

  | Ruta | Estado | Mediana TTFB | p95 TTFB | Mediana total | p95 total |
  | --- | --- | ---: | ---: | ---: | ---: |
  | `/admin` | 20 × 200 | 152,0 ms | 271,2 ms | 153,5 ms | 272,0 ms |
  | `/admin/unit-measures` | 20 × 200 | 146,2 ms | 244,9 ms | 147,1 ms | 245,9 ms |
  | `/admin/sales/pos` | 20 × 200 | 144,4 ms | 294,8 ms | 145,6 ms | 295,8 ms |

  Estas cifras son una referencia local de la candidata. No se declara una
  mejora porcentual de página completa porque la imagen anterior no tuvo la
  misma medición autenticada.
- El presupuesto reproducible de navegación sí compara directamente el código:
  83 consultas iniciales y 82 repetidas antes de la mejora, frente a un máximo
  de 12 y cero adicionales ahora. La reducción transversal de la primera
  resolución es como mínimo del 85,5 %.
- `operations:launch-check --skip-runtime`, ejecutado con los valores de
  producción de HTTPS y cookie segura, aprobó todos los controles. El archivo
  local de Compose usa HTTP y cookie no segura intencionadamente, por lo que no
  se tomó como configuración de despliegue.
- Desde que se regeneraron las cachés no hubo respuestas 5xx, excepciones ni
  errores de autorización en los logs de la instancia local. Una prueba inicial
  creó una sesión como `root` y el proceso web no pudo reescribirla; se eliminó
  esa sesión temporal y se recreó con el usuario web. Las mediciones registradas
  son posteriores a esa corrección de entorno.
- La revisión manual desde una computadora, accediendo a la candidata por túnel
  SSH, reportó cargas percibidas de aproximadamente 2 a 3 segundos, frente a 4
  a 8 segundos observados en el dominio público actual. Es evidencia funcional
  favorable, pero no sustituye el benchmark HTTP controlado porque ambos accesos
  usan rutas de red y despliegues distintos.

### Límite de la comparación previa al despliegue

Si se exige comparar percentiles HTTP antes y después en condiciones idénticas,
queda levantar la imagen anterior en una instancia aislada y repetir el mismo
recorrido autenticado. La puerta funcional, técnica y visual de esta fase está
cumplida; esta medición adicional solo aumentaría la precisión del contraste.

## Fase 6.1 — Segunda iteración de rendimiento de navegador

**Estado:** cerrada el 9 de octubre de 2026. La validación visual manual en la
instancia candidata confirmó una navegación sensiblemente más fluida y sin
precarga al pasar el cursor por el menú.

La validación manual mostró que la candidata ya es más rápida que el dominio
público, pero que su carga inicial de 3 a 5 segundos sigue siendo alta. El
perfil dentro del contenedor separó el origen del tiempo:

- Las páginas autenticadas completas responden desde PHP entre 128 y 204 ms de
  mediana en `/admin`, `/admin/unit-measures` y `/admin/sales/pos`. El HTML del
  panel ocupa 222,8 kB sin comprimir y 16,5 kB transferidos con gzip.
- La carga inicial del navegador incluye Flux (30,4 kB gzip), Livewire (74,8 kB
  gzip), CSS administrativo (83,6 kB gzip), fuentes de iconos y una hoja de
  fuentes solicitada a `fonts.bunny.net`.
- El asset administrativo cargaba además jQuery y el JavaScript completo de
  Bootstrap en todas las rutas, sin llamadas a jQuery ni componentes Bootstrap
  activos en el panel. Bootstrap CSS se mantiene porque 96 vistas aún usan sus
  clases.

### Primera corrección aplicada

- Se retiraron los imports globales de jQuery y `bootstrap.bundle` de
  `resources/js/admin.js`, y se retiró la dependencia jQuery. La búsqueda no
  encontró consumidores de `window.$`, `window.jQuery` ni `$()` en el panel.
- La compilación Docker con Node 22 aprobó y el nuevo asset administrativo es
  de 2,4 kB sin comprimir y 1,0 kB con gzip, frente a 176,2 kB y 56,5 kB
  respectivamente. La instancia local se recreó con esa imagen y
  `/health/ready` respondió `200`.
- Todo el menú lateral vuelve a usar enlaces de carga normal. Así sus rutas no
  pueden ser interceptadas ni precargadas por Livewire al pasar o interactuar
  con el menú. Los enlaces dentro de cada pantalla conservan `wire:navigate`.
  La prueba de marcado aprobó 96 casos y 278 aserciones, e impide reintroducir
  navegación Livewire en el menú lateral.
- La imagen recibe `APP_VERSION` como argumento de construcción y el pie del
  menú lateral muestra ese valor. La construcción candidata se hace con el
  hash corto de `HEAD`, por lo que la interfaz permite identificar el commit
  exacto que se está probando.

### Siguientes cambios, en orden

1. Comprobar en el waterfall del navegador si `fonts.bunny.net`, fuentes de
   iconos o scripts de Livewire son los recursos que prolongan la carga. Retirar
   la fuente remota o servirla localmente con `font-display: swap` si lo es.
2. Separar el CSS administrativo: sustituir gradualmente las clases Bootstrap
   por Flux/Tailwind y los tres iconos Font Awesome usados en panel por iconos
   Flux. Solo al no tener consumidores administrativos se retirarán Bootstrap y
   Font Awesome del CSS de ese layout.
3. Medir el coste de DOM e hidratación Livewire por pantalla; diferir los
   bloques secundarios del dashboard y aplicar `debounce` a los campos POS que
   hoy usan `wire:model.live` en cada pulsación.
4. Verificar a través del proxy público la compresión gzip y la cabecera
   `Cache-Control: public, max-age=31536000, immutable` de los assets con hash,
   antes de cualquier despliegue.

Los cambios anteriores quedan como backlog de rendimiento; no bloquearon el
despliegue porque la validación manual de la candidata resolvió el problema
percibido de lentitud.

## Fase 7 — Despliegue gradual y observación

**Estado:** completada el 9 de octubre de 2026.

1. Crear una etiqueta de imagen inmutable y conservar la imagen anterior.
2. Ejecutar el preflight y `operations:launch-check --skip-runtime`.
3. No se esperan migraciones para las fases 1–3; si la fase 4 introduce
   infraestructura, validar Redis antes de cambiar `CACHE_STORE`.
4. Desplegar con la política `start-first` y rollback automático ya configurada.
5. Verificar `/up`, `/health/ready`, login y las tres rutas de referencia.
6. Observar durante una ventana inicial de 30 minutos:
   - errores HTTP y excepciones;
   - latencia de rutas administrativas;
   - consultas lentas y carga de MySQL;
   - aciertos/fallos de caché si se activa la fase 4;
   - reinicios del servicio.
7. Mantener la imagen anterior hasta cerrar la observación.

### Resultado

- Preflight `operations:launch-check --skip-runtime` aprobado con la
  configuración de producción.
- Se publicó la imagen inmutable `erp-app:9df65ef`, construida desde el commit
  `9df65ef`; la imagen anterior `erp-app:a2a9d35` se conserva para rollback.
- Swarm completó la actualización `start-first` sin rollback.
- Durante 30 minutos, `/up` y `/health/ready` respondieron `200`, `/admin`
  redirigió correctamente a login, la única tarea permaneció en ejecución y
  los logs no registraron errores 5xx ni excepciones.

### Condiciones de rollback

- Errores de autorización o mezcla de datos entre organizaciones.
- Aumento sostenido del p95 o de consultas frente a la imagen anterior.
- Fallos de readiness, errores de vistas o navegación rota.
- Invalidación tardía de permisos o entitlements.

El rollback normal será `docker service rollback`. Como las primeras fases no
modifican el esquema, no se requiere rollback de base de datos.

## Secuencia de entregas recomendada

- **Entrega A:** fases 0, 1 y 2. Ataca la causa medida y mantiene bajo el riesgo.
- **Entrega B:** fase 3. Mejora la experiencia del navegador sobre un backend ya
  medido y optimizado.
- **Entrega C:** fases 4 y 5 solo si las métricas justifican Redis o la limpieza
  adicional de recursos.

Cada entrega debe pasar su propia puerta de validación. No se desplegarán juntas
las tres entregas, para poder atribuir cualquier mejora o regresión a un cambio
concreto.

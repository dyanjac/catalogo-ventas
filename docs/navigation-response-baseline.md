# Línea base de respuesta de navegación

## Alcance

Rama `feature/respuesta-navegacion`, basada en `8aca09e`. Se revisaron el layout
administrativo, el menú, los servicios de contexto y autorización, y la
documentación del stack. Esta medición no cambia código ni datos de negocio.

El contenedor ERP disponible usa una imagen anterior al commit base. Se verificó
que `AppServiceProvider.php` y `OrganizationEntitlementService.php` son idénticos
entre la imagen y la rama; el layout administrativo difiere. Por ello, las cifras
de los servicios son una referencia útil, pero no equivalen al tiempo total de
una navegación en la versión de esta rama.

## Mediciones

Se ejecutó un script PHP temporal por entrada estándar dentro del contenedor
existente. El script inicializó Laravel, escuchó eventos de consultas SQL y
ejecutó solo lecturas. Seleccionó un usuario superadministrador sin mostrar sus
datos personales. No se inició una sesión ni se renderizó una página autenticada.

| Operación | Consultas | Tiempo SQL | Tiempo total |
| --- | ---: | ---: | ---: |
| `OrganizationContextService::forView()` | 9 | 37,6 ms | 41,3 ms |
| Repetición de `forView()` en el mismo proceso | 9 | 18,3 ms | 20,8 ms |
| `SecurityAuthorizationService::modulesForNavigation()` | 83 | 224,9 ms | 281,2 ms |
| Repetición de `modulesForNavigation()` en el mismo proceso | 82 | 158,5 ms | 201,1 ms |

La navegación mostró 21 módulos. De las 83 consultas de su primera ejecución,
36 fueron comprobaciones de esquema; 18 consultaron capacidades, 9 suscripciones
y 9 entitlements. Los resultados son una muestra puntual, no percentiles ni un
benchmark de páginas completas. Los tiempos varían con la carga y la caché.

Una petición HTTP sin sesión a `/admin` devolvió `302` hacia el login, con
aproximadamente 120 ms hasta el primer byte. Esa cifra no representa una visita
autenticada al panel.

## Hallazgos en el código

1. `app/Providers/AppServiceProvider.php` registra un `View::composer('*')`. Por
   cada vista preparada llama a `OrganizationContextService::forView()` y a los
   servicios de comercio. `forView()` vuelve a resolver la organización para
   construir sus campos. Esto puede multiplicar las consultas al renderizar
   layouts, componentes y parciales.
2. `Modules/Security/app/Services/SecurityAuthorizationService.php` filtra los
   módulos visibles mediante `OrganizationEntitlementService::hasModuleCapability()`.
   Este servicio vuelve a verificar el esquema y resolver capacidades en cada
   comprobación. La repetición de 82 consultas en el mismo proceso confirma que
   el resultado completo de navegación no se reutiliza allí.
3. El menú principal ya usa `wire:navigate.hover`, pero muchos enlaces internos
   de las vistas administrativas son enlaces normales. Las transiciones pueden
   sentirse distintas según el punto de partida.
4. El panel carga Flux, Tailwind, Bootstrap, Font Awesome y jQuery. AdminLTE está
   declarado en `package.json`, pero no se importa en los recursos administrativos
   revisados. Retirarlo por sí solo no resolvería las consultas observadas.

## Orden recomendado para la mejora

1. Medir peticiones autenticadas representativas: tiempo hasta el primer byte,
   consultas, renderizado y experiencia del navegador. Comparar al menos dos
   módulos sencillos y uno pesado.
2. Resolver el contexto de organización una vez por petición y limitar el
   compositor global a las vistas que realmente necesitan esos datos. Conservar
   el cambio explícito de organización dentro de la petición.
3. Calcular las capacidades de la organización una vez por petición o leerlas en
   bloque; conservar las reglas de invalidez cuando cambie un plan o entitlement.
4. Unificar la navegación interna compatible con Livewire y revisar scripts
   ligados a cargas completas de página.
5. Después de medir el resultado, reducir dependencias CSS/JS que ya no se usan.
   Una migración completa de framework visual requiere evidencia adicional.

Referencias: [navegación en Livewire 4](https://livewire.laravel.com/docs/4.x/navigate)
y [observación de consultas en Laravel 12](https://laravel.com/framework/docs/12.x/database).

## Resultado de las fases 1 a 3

La implementación del 8 de octubre de 2026 conserva esta línea base como punto
de comparación y añade una prueba de presupuesto reproducible. La construcción
de la navegación queda limitada a un máximo de 12 consultas en su primera
resolución y a cero consultas adicionales en la segunda resolución del mismo
ciclo. Antes del cambio se observaron 83 y 82 consultas, respectivamente.

El contexto de organización queda limitado a un máximo de 2 consultas en su
primera resolución y a cero consultas adicionales al reutilizarlo. La memoria
local se reinicia cuando cambia la petición, el usuario autenticado, el
storefront público o el contexto explícito.

El compositor global fue reemplazado por un compositor dirigido a los layouts y
vistas que consumen los datos de organización y comercio. Se validaron panel,
tienda pública, carrito, branding y aislamiento entre organizaciones mediante la
suite automatizada. El resultado final fue de 170 pruebas y 770 aserciones
aprobadas, con 5 pruebas omitidas por sus condiciones de entorno.

La imagen candidata `erp-app:navigation-phase2-test` se construyó correctamente,
incluido `npm run build`. La medición HTTP autenticada con mediana y percentil 95
continúa pendiente y debe ejecutarse antes de cualquier despliegue.

La fase 3 uniformó la navegación interna del panel con Livewire en 115 enlaces
GET seguros y eliminó la precarga por `hover`. Descargas, cierre de sesión y
destinos que cambian de layout mantienen cargas completas. Los scripts propios
de billing, transporte y contabilidad se trasladaron al asset administrativo y
se adaptaron al evento `livewire:navigated`.

La validación automatizada de esta fase cubre el contrato de rutas y acciones de
los controladores modulares, una solicitud HTTP con el encabezado real de
navegación de Livewire y las reglas del marcado administrativo. La suite completa
aprobó 262 pruebas y 1.632 aserciones, con 5 omisiones por entorno. La validación
visual en escritorio y la medición autenticada continúan pendientes para la fase
6.

## Medición autenticada de la candidata — fase 6

El 9 de octubre de 2026 se midió `erp-app:feature-respuesta-navegacion` en la
instancia local de Compose. Se utilizó una sesión de superadministrador local,
sin exponer credenciales, y el encabezado `X-Livewire-Navigate: 1`. Cada ruta
recibió 20 solicitudes consecutivas y todas respondieron `200`.

| Ruta | Mediana TTFB | p95 TTFB | Mediana total | p95 total |
| --- | ---: | ---: | ---: | ---: |
| `/admin` | 152,0 ms | 271,2 ms | 153,5 ms | 272,0 ms |
| `/admin/unit-measures` | 146,2 ms | 244,9 ms | 147,1 ms | 245,9 ms |
| `/admin/sales/pos` | 144,4 ms | 294,8 ms | 145,6 ms | 295,8 ms |

La línea base anterior contiene solo lecturas de servicios, no el mismo
recorrido HTTP autenticado de la imagen previa. Por ello, esta tabla establece
una referencia de la candidata y no una comparación porcentual de páginas
completas. El indicador directamente comparable es el presupuesto de consultas
de navegación: de 83 consultas iniciales y 82 repetidas a un máximo de 12 y
cero adicionales en el mismo ciclo.

La revisión visual manual desde escritorio también percibió cargas de 2 a 3
segundos en la candidata por túnel SSH, frente a 4 a 8 segundos en el dominio
público que todavía sirve la imagen anterior. La diferencia de red impide
tratar esa observación como benchmark controlado, pero confirma que la
navegación administrativa es funcional y se percibe más fluida.

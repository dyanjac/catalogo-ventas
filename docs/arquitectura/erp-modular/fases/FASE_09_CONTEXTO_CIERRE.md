# FASE 09 â€” Contexto de cierre

## Resultado

FASE 09 implementa el dominio comercial de suscripciones de clientes, separado de las suscripciones internas que habilitan capacidades SaaS del ERP.

Quedaron operativos:

- contratos tenant-safe para productos de tipo `suscripcion`;
- periodos de servicio mensuales, trimestrales y anuales;
- calendario durable con importes en cÃ©ntimos y residuo determinista;
- renovaciÃ³n idempotente bajo lock;
- cancelaciÃ³n inmediata o al final del periodo;
- ajustes negativos auditables y limitados al ingreso ya devengado;
- reclamo por lotes con lease, reintentos y job Ãºnico;
- comandos `subscriptions:accruals:dispatch` y `subscriptions:renewals:dispatch` con `--organization`, `--through`, `--limit` y `--dry-run`;
- scheduler UTC: devengamientos cada 5 minutos y renovaciones cada hora;
- interfaz administrativa de alta, consulta, renovaciÃ³n, cancelaciÃ³n y ajuste;
- capability `subscriptions.recurring`, mÃ³dulo RBAC y permisos por rol;
- cuenta jerÃ¡rquica `deferred_revenue` en producto, categorÃ­a y empresa;
- factura anticipada vinculada 1:1 y reclasificaciÃ³n ingreso / ingreso diferido con snapshot de cuentas;
- evento `service_accrued`: ingreso diferido / ingreso devengado.

## Evidencia

- 8 pruebas nuevas pasan con 34 aserciones.
- RegresiÃ³n dirigida de Accounting, resolver contable y entitlements: 13 pruebas y 61 aserciones pasan.
- Suite global: 101 pruebas pasan, 461 aserciones, 4 pruebas MySQL opt-in omitidas.
- Persisten 5 fallos histÃ³ricos ajenos a FASE 09: dos expectativas de acceso admin, una redirecciÃ³n de paleta, el ExampleTest sin base migrada y una expectativa equivalente en Core.
- `git diff --check` sin errores.
- Rutas administrativas, comandos y schedules fueron descubiertos por Artisan.

## OperaciÃ³n

Ejecutar despuÃ©s del despliegue:

```bash
php artisan migrate
php artisan db:seed --class="Modules\\Security\\Database\\Seeders\\SecurityDatabaseSeeder"
php artisan queue:work --queue=subscriptions,accounting,default --tries=3
php artisan schedule:work
```

Antes de emitir una suscripciÃ³n anticipada, configurar las cuentas por defecto o por producto/categorÃ­a. El plan mÃ­nimo contable crea `496101 Ingresos diferidos por suscripciones`.

## Pendiente controlado

- Pruebas reales MySQL/InnoDB de carreras y `SKIP LOCKED` permanecen opt-in.
- La emisiÃ³n fiscal recurrente puede vincularse a `billing_document_id` del periodo; la generaciÃ³n automÃ¡tica del comprobante y el cobro automÃ¡tico quedan fuera de esta primera iteraciÃ³n.
- No existe prorrateo automÃ¡tico ni cambio de tarifa dentro de un periodo ya creado.

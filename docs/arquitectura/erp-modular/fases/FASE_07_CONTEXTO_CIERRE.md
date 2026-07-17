# FASE 07 - Contexto de cierre

## Resultado

FASE 07 implementa GRE remitente y transportista como un agregado documental independiente. Soporta ventas, transferencias, devoluciones y guias sin comprobante previo, manteniendo la regla central: una GRE no mueve inventario.

## TERMINADO

- Modulo `Transport` registrado y habilitado.
- Configuracion multiempresa para simulacion y Greenter productivo.
- Credenciales cifradas, validacion versionada por hash y bloqueo de produccion insegura.
- Endpoints SUNAT fijos, lista cerrada de credenciales y certificado confinado al directorio privado GRE.
- Capability SaaS `transport.gre`, bloqueo de organizaciones no activas y huella de configuracion por guia/job.
- GRE remitente `09` con serie `Txxx`.
- GRE transportista `31` con serie `Vxxx`.
- Motivos del catalogo SUNAT 20, incluido traslado interno `04`.
- Transporte publico y privado con validaciones diferenciadas.
- Guias vinculables a comprobantes, documentos de inventario, transferencias y GRE remitente.
- Guias sin comprobante y sin salida fisica previa.
- Referencia inmutable a GRE remitente externa para el flujo normal del transportista.
- Snapshots documentales e items inmutables.
- Idempotencia durable y correlativos bloqueables por organizacion/tipo/serie.
- Envio asincrono con job unico, estado de reclamacion `submitting` y consulta de ticket.
- Estado fail-closed `uncertain` para resultados ambiguos; una guia con ticket no se reenvia y una consulta transitoria conserva `submitted`.
- Proveedor determinista de simulacion y adaptador `Greenter\Api` para produccion.
- XML/CDR en almacenamiento privado con hashes de integridad.
- Bitacora de transmisiones append-only.
- Permisos RBAC, alcance por sucursal y navegacion administrativa.
- Permiso separado para exportar XML/CDR; almacenes no administra credenciales.
- Formularios de creacion, listado, detalle, envio, consulta, descarga y configuracion.
- Triggers SQLite/MySQL para aislamiento tenant e inmutabilidad.
- Rollback bloqueado cuando existe evidencia GRE.
- ADR-007 y pruebas funcionales de la fase.

## EN PROGRESO

- Ningun entregable de codigo de FASE 07 queda en progreso.
- La activacion real queda como tarea operativa por empresa: registrar credenciales, certificado, validar configuracion y ejecutar el worker de la cola `transport`.

## NO INICIADO

- Anulacion/baja formal de GRE ya aceptadas.
- Representacion impresa/PDF con QR.
- Reintentos programados y tablero de observabilidad para tickets demorados.
- Pruebas contractuales contra el ambiente beta/produccion de SUNAT; no se ejecutan sin credenciales autorizadas.

## BLOQUEADO

- La emision productiva esta intencionalmente bloqueada hasta validar credenciales OAuth y certificado PEM de cada organizacion.
- No hay bloqueos para usar el proveedor de simulacion ni para continuar con FASE 08.

## Archivos clave

- `Modules/Transport/database/migrations/2026_07_17_100000_create_transport_guide_domain.php`
- `Modules/Transport/app/Services/TransportGuideService.php`
- `Modules/Transport/app/Services/GreenterTransportGuideProvider.php`
- `Modules/Transport/app/Services/GreenterDespatchFactory.php`
- `Modules/Transport/app/Jobs/IssueTransportGuideJob.php`
- `Modules/Transport/app/Models/TransportGuide.php`
- `Modules/Transport/routes/web.php`
- `tests/Feature/TransportGuideWorkflowTest.php`
- `docs/arquitectura/erp-modular/decisiones/ADR-007-GUIAS-DE-REMISION-Y-TRASLADOS.md`

## Validacion ejecutada

- Laravel arranca y reconoce el modulo `Transport`.
- 11 rutas administrativas registradas.
- Compilacion de vistas Blade aprobada.
- Suite FASE 07: 8 pruebas, 49 aserciones, aprobada.
- Regresion extendida de entitlement + FASE 05/06 + checkout + FASE 07: 26 pruebas, 128 aserciones, aprobada.
- Concurrencia MySQL/InnoDB de idempotencia y correlativos implementada como prueba opt-in; omitida en el entorno SQLite actual.
- Suite global: 87 pruebas aprobadas, 5 fallos historicos no relacionados, 3 pruebas MySQL opt-in omitidas y 397 aserciones.
- Laravel Pint aprobado sobre los archivos de la fase.
- `composer validate --no-check-publish` aprobado.
- Compilacion Vite aprobada; el entorno advierte que Node 18.20.8 debe actualizarse a 20.19+ o 22.12+.

Los cinco fallos globales preexistentes corresponden a la redireccion admin esperada en `/login`, dos accesos super-admin bloqueados por RBAC, el reset de paleta de una organizacion suspendida y `ExampleTest` sin migraciones para `security_branches`. La suite FASE 07 y sus regresiones focales no presentan fallos.

## Operacion y activacion

La organizacion comienza en simulacion. Antes de cambiar a produccion debe:

1. Configurar `client_id`, `client_secret`, usuario SOL, clave SOL, RUC y certificado PEM.
2. Validar la configuracion desde Administracion > Transporte y GRE > Configuracion.
3. Mantener un worker para la cola configurada, por defecto `transport`.
4. Emitir y verificar una GRE controlada antes de habilitar el flujo masivo.

## Proxima fase

FASE 08 abordara eventos economicos y contabilidad: estados pendiente/procesado/error, idempotencia, asiento por comprobante, costo de venta, cobro, devolucion, reversion y configuracion contable por producto, categoria y empresa.

Prompt recomendado para continuar:

> Vamos con la FASE 08: implementa eventos economicos idempotentes y su procesamiento contable, incluyendo asientos de comprobante, costo de venta, cobro, devolucion y reversion, con configuracion por producto, categoria y empresa. Conserva aislamiento multiempresa, trazabilidad, rollback seguro y pruebas de concurrencia; no contabilices historico automaticamente.

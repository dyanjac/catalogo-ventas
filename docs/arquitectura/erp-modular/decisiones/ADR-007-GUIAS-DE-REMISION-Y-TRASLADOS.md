# ADR-007 - Guias de remision y traslados

## Estado

Aceptado el 2026-07-17.

## Contexto

El ERP necesita emitir GRE remitente y transportista para ventas, traslados internos, devoluciones y operaciones que no tienen comprobante previo. Una GRE es evidencia tributaria y documental, pero no representa por si sola una salida o entrada fisica de inventario.

SUNAT exige que la GRE se emita antes del inicio del traslado y distingue los documentos del remitente y del transportista. La integracion moderna utiliza autenticacion OAuth, envio asincrono y consulta de ticket. Por ello no se reutiliza el agregado `billing_documents` ni el flujo SOAP de comprobantes.

## Decision

Se crea el modulo `Transport` como limite funcional independiente de Billing e Inventory.

- `transport_guides` conserva la identidad, snapshots, vinculos internos y estado electronico de cada GRE.
- `transport_guide_items` conserva el detalle documental inmutable.
- `transport_guide_transmissions` es una bitacora append-only de encolado, envio y consulta.
- `transport_settings` mantiene configuracion por organizacion; las credenciales se cifran mediante el cast `encrypted:array`.
- `transport_guide_counters` asigna correlativos bajo bloqueo por organizacion, tipo y serie.
- Los XML y CDR se escriben en el disco privado `local`; la base solo conserva rutas y hashes SHA-256.
- La simulacion es el modo inicial. Produccion queda bloqueada si la configuracion Greenter no fue validada o cambio desde su validacion.
- La capacidad comercial `transport.gre` y el estado activo de la organizacion se comprueban al crear, encolar, enviar y consultar.

## Reglas de dominio

1. La guia puede vincularse a un documento de inventario, transferencia, comprobante o GRE remitente, siempre dentro de la misma organizacion.
2. Una guia de transferencia interna usa el motivo SUNAT `04` y debe reproducir exactamente productos y cantidades de la transferencia.
3. Una guia remitente puede existir sin comprobante previo y sin operacion fisica vinculada.
4. Una guia transportista requiere una GRE remitente aceptada, salvo excepcion expresamente habilitada y justificada.
5. Crear, encolar, enviar, aceptar, observar o rechazar una GRE nunca llama servicios de movimiento, reserva o transferencia de inventario.
6. La identidad documental y los items son inmutables desde su creacion. Una correccion genera otra guia.
7. La idempotencia se aplica por `(organization_id, idempotency_key)` y el replay solo es valido si conserva el mismo hash de payload.
8. Los jobs llevan `organization_id` y `guide_id`; el agregado pasa a `submitting` bajo bloqueo antes de cualquier llamada externa.
9. Una excepcion ambigua de envio o la terminacion del worker deja la guia en `uncertain`; ese estado no permite reenvio automatico.
10. Una falla de consulta conserva `submitted` y su ticket. Una guia que ya tiene ticket nunca vuelve al flujo de envio.
11. Una GRE transportista puede referenciar una GRE remitente externa mediante tipo, numero y RUC emisor inmutables.

## Estados

`draft -> ready -> queued -> submitting -> submitted -> accepted|accepted_with_observation|rejected`

`error` admite reintento controlado solo si no existe ticket. `uncertain` exige conciliacion manual antes de decidir una correccion. `voided` queda reservado para el flujo formal de baja o correccion; no elimina evidencia.

## Catalogos y proveedor

- Tipos: remitente `09`, serie `Txxx`; transportista `31`, serie `Vxxx`.
- Modalidad: transporte publico `01`, transporte privado `02`.
- Motivos de traslado: catalogo SUNAT 20 versionado en configuracion.
- Proveedor productivo: `Greenter\Api`, con envio y consulta asincrona de ticket.
- Los endpoints OAuth/CPE son constantes oficiales del servidor; no pueden ser enviados desde la UI.

Referencias normativas y tecnicas:

- [SUNAT - Guia de Remision Electronica](https://cpe.sunat.gob.pe/node/171)
- [SUNAT - GRE Remitente](https://cpe.sunat.gob.pe/node/116)
- [SUNAT - GRE Transportista](https://cpe.sunat.gob.pe/node/122)
- [Greenter - configuracion de produccion](https://greenter.dev/production/)

## Seguridad e integridad

- Todas las consultas de UI y servicios se acotan por organizacion.
- Triggers SQLite/MySQL rechazan vinculos cross-tenant en insercion y actualizacion.
- Triggers y eventos Eloquent bloquean la mutacion/eliminacion de items y transmisiones.
- La migracion no puede revertirse mientras exista una GRE, porque hacerlo eliminaria evidencia tributaria.
- Los permisos separan lectura, creacion, envio, consulta y configuracion.
- La descarga XML/CDR usa un permiso `transport.guides.export` separado.

## Consecuencias

La operacion de almacen permanece como unica fuente de verdad del movimiento fisico. La aceptacion SUNAT puede ocurrir antes o despues de la confirmacion operativa sin introducir doble descuento. A cambio, produccion requiere configurar un worker de cola, credenciales OAuth, certificado PEM y una politica operativa para observar tickets pendientes y errores.

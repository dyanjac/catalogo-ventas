# ADR-011 — Conciliación y observabilidad operativa

## Estado

Aceptado — 2026-07-17.

## Contexto

El ERP dispone de ledgers de inventario, documentos formales, eventos económicos, asientos y activaciones históricas. La integridad transaccional evita gran parte de las desviaciones, pero no detecta fallas externas, workers interrumpidos, datos históricos defectuosos ni cambios directos en base de datos.

## Decisión

Se incorpora el módulo transversal `Operations`, sin convertirlo en dueño de los dominios conciliados.

- Cada corrida queda sellada por organización, correlación, instante y disparador.
- Las lecturas de una corrida usan un snapshot `REPEATABLE READ` en MySQL/MariaDB para no mezclar saldos y movimientos confirmados en instantes distintos.
- Los hallazgos son append-only y contienen solo identificadores y valores técnicos acotados.
- Un fingerprint estable deduplica incidentes entre corridas; un incidente puede abrirse, repetirse, reconocerse, resolverse y reabrirse sin borrar su historia.
- La conciliación nunca repara automáticamente evidencia financiera o de inventario.
- La recuperación de eventos abandonados es una herramienta separada, con simulación predeterminada, tenant explícito y confirmación para ejecutar.
- La ejecución periódica usa un job único por organización, `withoutOverlapping` y `onOneServer`.
- `/up` permanece como liveness y `/health/ready` valida dependencias sin exponer datos del tenant.
- Los logs operativos se escriben como JSON y omiten payloads económicos, SQL, secretos y datos personales.

## Invariantes cubiertos

- Kardex: alcance, secuencia, ecuación, continuidad, costo, reversas y cabeza contra saldo.
- Documentos: confirmación, movimiento por ítem, signo/cantidad, referencia, compensación y huérfanos.
- Contabilidad: evento/asiento recíprocos, tenant/hash, periodo, snapshot, totales, partida doble, reversas y activación histórica.
- Base de datos: no insertar líneas en asientos publicados; vínculos evento/asiento y líneas/asiento deben conservar el tenant.
- Reversas: sus eventos y asientos originales pertenecen al mismo tenant y todo estado `reversed` tiene una compensación procesada.

## Consecuencias

La evidencia operativa crecerá y requerirá una política futura de archivo, no borrado improvisado. Las notificaciones externas quedan desacopladas: los incidentes persistidos son la fuente para integrar correo, Slack u otro canal posteriormente.

La ejecución distribuida exige cache compartida y `retry_after` superior al timeout máximo del job. El valor mínimo adoptado es 960 segundos para un timeout de 900 segundos.

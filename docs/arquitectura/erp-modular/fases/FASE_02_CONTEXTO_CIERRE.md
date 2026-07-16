# FASE 02 — CONTEXTO DE CIERRE

## 1. Identificación

- Proyecto: `catalogo-ventas`.
- Fase: 02 — Normalización del producto.
- Fecha: 2026-07-16.
- Estado: IMPLEMENTADA Y VALIDADA EN SU ALCANCE.
- Rama: `master`.
- Commit inicial: `19c007e2e40f21bdeded943d9e9b021c24972c27`.
- Commit final: SIN COMMIT; FASE 01 y FASE 02 permanecen juntas en el worktree.

## 2. Objetivo de la fase

Separar la clasificación y política contable del producto de sus datos operativos de inventario, incorporar tipos explícitos y resolver tratamiento/cuentas con prioridad producto → categoría → empresa sin romper datos ni consumidores existentes.

## 3. Alcance implementado

- Tipos: `bien_fisico`, `servicio`, `suscripcion`, `digital`, `kit`, `informativo`.
- Tratamientos: `HEREDAR`, `AUTOMATICO`, `MANUAL`, `NO_APLICA`, `PENDIENTE_CONFIGURACION`.
- Casts Eloquent con backed enums en producto, categoría y configuración contable.
- Herencia de tratamiento y cinco cuentas contables por producto, categoría y organización.
- Aislamiento tenant explícito al resolver categoría y configuración empresarial.
- Integración del resolvedor con el autoasiento de ventas.
- Retiro de `stock` y `average_price` de los inputs validados y del formulario normal de producto.
- Configuración de tratamiento/cuentas en categorías y configuración contable empresarial.
- Backfill compatible desde `requires_accounting_entry`.

## 4. Fuera de alcance

- Ledger inmutable, stock inicial, conciliación o nueva fuente de saldos de FASE 03.
- Reservas, expiración, bloqueo o control de stock negativo de FASE 04.
- COGS automático, eventos económicos y bandeja persistente de revisión de FASE 08.
- Eliminar físicamente `products.stock`, `products.average_price` o `requires_accounting_entry`.
- Cambiar el comportamiento de inventario según `tracksInventory()`; solo se expuso la semántica.

## 5. Decisiones tomadas

- Decisión: enums respaldados almacenados como string. Motivo: contrato tipado sin bloquear evolución del dominio mediante enum nativo de base de datos.
- Decisión: resolvedor en Accounting. Motivo: Catalog conserva datos maestros y no depende del módulo contable.
- Decisión: cuentas heredadas campo por campo. Motivo: permitir override parcial sin perder defaults válidos.
- Decisión: cadena sin tratamiento terminal → `PENDIENTE_CONFIGURACION`. Motivo: evitar autoasientos implícitos.
- Decisión: `MANUAL` o pendiente en una venta mixta detiene el autoasiento completo. Motivo: evitar asientos parciales.
- Decisión: mantener fallbacks contables legacy solo cuando no existe un código explícito. Motivo: compatibilidad; un código inválido no debe ocultarse con otra cuenta.

## 6. Arquitectura resultante

- Catalog posee `ProductType` y `ProductAccountingTreatment`.
- Product y Category exponen configuración declarativa mediante enums/cuentas.
- AccountingSetting contiene la política empresarial terminal y cuentas por defecto.
- `ProductAccountingConfigurationResolver` carga categoría solo si comparte `organization_id` y consulta settings por la organización del producto.
- `ResolvedProductAccountingConfiguration` devuelve tratamiento, origen, cuentas y origen de cada cuenta.
- `SalesAccountingService` conserva el entitlement de FASE 01 y usa la configuración resuelta antes de construir líneas.

## 7. Modelo de datos resultante

| Tabla | Columnas nuevas | Compatibilidad / rollback |
|---|---|---|
| `products` | `product_type`, `accounting_treatment` | Backfill true→AUTOMATICO, false→NO_APLICA; no se eliminan columnas legacy. |
| `categories` | `accounting_treatment`, cinco `account_*` | Defaults en HEREDAR y cuentas nullable. |
| `accounting_settings` | `product_accounting_treatment`, cinco `default_account_*` | Default seguro PENDIENTE_CONFIGURACION. |

Migraciones:

- `Modules/Catalog/database/migrations/2026_07_16_130000_normalize_product_classification_and_accounting.php`.
- `Modules/Accounting/database/migrations/2026_07_16_130100_add_product_accounting_defaults_to_settings.php`.

## 8. Archivos creados

- `Modules/Catalog/app/Enums/ProductType.php`.
- `Modules/Catalog/app/Enums/ProductAccountingTreatment.php`.
- `Modules/Accounting/app/Data/ResolvedProductAccountingConfiguration.php`.
- `Modules/Accounting/app/Services/ProductAccountingConfigurationResolver.php`.
- Dos migraciones de FASE 02.
- `tests/Unit/ProductClassificationTest.php`.
- `tests/Feature/ProductAccountingConfigurationResolverTest.php`.
- `tests/Feature/SalesAccountingTenantIsolationTest.php`.
- `tests/Feature/AccountingPayloadCompatibilityTest.php`.
- `decisiones/ADR-002-NORMALIZACION-PRODUCTO-Y-HERENCIA-CONTABLE.md`.

## 9. Archivos modificados

- Entidades Product/Category y modelo AccountingSetting: fillable, casts y semántica.
- Requests Store/Update: enums y exclusión de stock/costo promedio.
- ProductController y formulario/detalle: clasificación y mantenimiento no operativo.
- CategoryController y formulario: política heredable.
- AccountingSettingsController y vista: política empresarial terminal.
- SalesAccountingService y provider: resolución e inyección.
- SalesAccountingService: scoping explícito por `order.organization_id` para ejecución segura en workers.
- AccountingAccountController: reset limpia referencias en los tres niveles.
- ProductFactory: defaults de clasificación.
- Tres migraciones históricas: backfills portables para SQLite/MySQL.
- Pruebas FASE 01: serialización correcta del JSON de pivote.
- Cierre FASE 01: estado de validación actualizado.

## 10. Flujos implementados

Resolución
→ leer tratamiento de producto
→ si HEREDAR, leer categoría válida del mismo tenant
→ si HEREDAR, leer configuración de la organización
→ si no hay terminal, devolver PENDIENTE_CONFIGURACION.

Cuentas
→ resolver cada cuenta por separado
→ producto específico
→ categoría
→ configuración empresarial
→ fallback legacy solo si no hubo código explícito.

Mantenimiento
→ validar tipo y tratamiento
→ sincronizar booleano legacy
→ ignorar cualquier `stock` o `average_price` enviado
→ conservar cantidades/costo existentes.

Autoasiento
→ validar entitlement y estado tenant de FASE 01
→ resolver política por producto
→ excluir NO_APLICA
→ detener ante MANUAL/PENDIENTE o cuenta explícita inválida
→ publicar solo configuración AUTOMATICO completa.

## 11. Validaciones realizadas

- `php -l` en todos los archivos PHP creados/modificados: exitoso.
- Pint sobre los archivos PHP de FASE 02 y pruebas relacionadas: exitoso después de aplicar formato.
- `git diff --check`: exitoso.
- Migración completa desde cero en SQLite: exitosa.
- Rollback de las dos migraciones FASE 02 con `--step=2`: exitoso.
- Compilación de vistas Blade con `php artisan view:cache`: exitosa.
- Suite enfocada FASE 01/02: 20 pruebas, 83 aserciones, 0 fallos.
- Suite global: ejecutable; conserva fallos preexistentes fuera del alcance descritos en la sección 12.

## 12. Errores encontrados

- Resuelto: `vendor/autoload.php` y dependencias ausentes mediante Composer en el contenedor con LDAP.
- Resuelto: `UPDATE JOIN` específico de MySQL en `2026_02_27_140000_add_product_sku_to_product_images_table.php`.
- Resuelto: dos backfills tenant con updates joined no portables en migraciones `2026_03_24_190000` y `2026_03_24_200000`.
- Resuelto: fixtures FASE 01 enviaban arrays al pivote JSON sin serializar.
- Resuelto tras revisión independiente: autoasiento asíncrono dependía del tenant implícito del worker; ahora todas las consultas y escrituras usan la organización de la orden, rechaza productos vinculados desde otro tenant y existe una prueba multi-tenant.
- Resuelto tras revisión independiente: payloads anteriores de categorías/settings podían recibir 422; los nuevos campos son opcionales para clientes legacy y preservan la configuración existente.
- Suite global restante: 7 pruebas históricas fallan por expectativas de login/entitlement antiguas, fixture de stock por sucursal, ausencia de `public/build/manifest.json` y un smoke test sin `RefreshDatabase`. No corresponden a la lógica nueva de FASE 02.

## 13. Riesgos pendientes

- `products.stock` y `average_price` siguen siendo leídos por fallbacks antiguos. Mitigación: FASE 03 migra a ledger/saldo y conciliación antes de retirarlos.
- `tracksInventory()` aún no gobierna checkout/movimientos. Mitigación: integrar después del ledger, con pruebas por tipo y sin introducir reservas antes de FASE 04.
- Los defaults legacy por flags/tipo de cuenta siguen activos cuando no hay código heredado. Mitigación: completar settings empresariales y medir usos antes de retirarlos.
- No hay bandeja persistente para PENDIENTE_CONFIGURACION. Mitigación: eventos económicos y revisión en FASE 08.

## 14. Deuda técnica pendiente

- Unificar aliases `App\Models\Product/Category` con entidades canónicas de Catalog.
- Añadir factories completas tenant-aware para Organization, Category y UnitMeasure.
- Corregir las siete pruebas globales preexistentes y generar assets Vite de pruebas.
- Exponer en UI una vista de configuración efectiva y origen de cada cuenta.
- Sustituir el espejo `requires_accounting_entry` cuando todos los consumidores usen el enum.

## 15. Compatibilidad y migración

- La migración añade antes de retirar; no renombra ni borra columnas actuales.
- Productos existentes conservan semántica de autoasiento mediante backfill del booleano.
- Productos nuevos heredan; empresa sin configuración termina pendiente, no automática.
- Requests legacy que envían solo `requires_accounting_entry` se traducen al tratamiento nuevo.
- Rollback elimina exclusivamente columnas/índices FASE 02; stock, costo promedio, booleano y cuentas de producto permanecen.
- Antes de rollback en producción debe exportarse cualquier configuración introducida en categorías/settings.

## 16. Configuración requerida

- Sin nuevas variables de entorno, jobs, colas o cron.
- Configurar el tratamiento terminal y cuentas por defecto desde Configuración contable por organización.
- El PHP CLI de Herd Lite sigue sin LDAP; Composer debe ejecutarse con el PHP del contenedor o una distribución con `ext-ldap`.

## 17. Estado actual de pruebas

- Nuevas pruebas FASE 02: 14 casos (8 unitarios/dataset y 6 feature).
- Validación combinada FASE 01/02: 20 pruebas, 83 aserciones, todas exitosas.
- Migración/rollback SQLite: exitosos.
- Vistas Blade: compilación exitosa.
- Suite global: 34 exitosas y 7 fallidas fuera de alcance; ver sección 12.
- Pint global: existen 107 incidencias de estilo preexistentes en 352 archivos; los archivos PHP de FASE 02 sí fueron formateados.

## 18. Criterios de aceptación

- [x] Tipos de producto explícitos y tipados.
- [x] Cinco tratamientos contables implementados.
- [x] Herencia producto → categoría → empresa por tenant.
- [x] Configuración parcial de cuentas resuelta campo por campo.
- [x] Compatibilidad del booleano y columnas legacy conservada.
- [x] Stock y costo promedio fuera del mantenimiento normal.
- [x] Autoasiento consume tratamiento efectivo sin asientos parciales ante revisión.
- [x] Migraciones aditivas y rollback verificados.
- [x] Pruebas enfocadas y compilación Blade exitosas.
- [x] Ledger y reservas no implementados.

## 19. Próxima fase recomendada

- Código: FASE 03.
- Nombre: Ledger de inventario y saldo objetivo.
- Objetivo: convertir movimientos en fuente inmutable, formalizar stock inicial/ajustes y migrar saldos con conciliación idempotente.
- Dependencias satisfechas: clasificación de producto y separación del mantenimiento operativo.
- Riesgos iniciales: coexistencia de `products.stock`, branch stocks, warehouse stocks e inventory movements; backfill sin duplicación.
- No iniciar reservas, expiración o concurrencia avanzada de FASE 04 dentro de FASE 03.

## 20. Prompt para continuar en una nueva ventana

```markdown
# CONTINUACIÓN DE IMPLEMENTACIÓN POR FASE

Trabajaremos únicamente la FASE 03 — Ledger de inventario y saldo objetivo.

Lee completamente antes de cambiar código:
- docs/arquitectura/erp-modular/00_CONTEXTO_MAESTRO_PROYECTO.md
- docs/arquitectura/erp-modular/02_ARQUITECTURA_OBJETIVO.md
- docs/arquitectura/erp-modular/03_PLAN_IMPLEMENTACION_POR_FASES.md
- docs/arquitectura/erp-modular/fases/FASE_02_CONTEXTO_CIERRE.md
- docs/arquitectura/erp-modular/decisiones/ADR-002-NORMALIZACION-PRODUCTO-Y-HERENCIA-CONTABLE.md

Verifica estado Git, migraciones, tablas y pruebas reales. Resume estado recibido, dependencias, riesgos, fuentes actuales de stock/costo y plan exacto antes de editar.

Alcance: ledger inmutable, saldo objetivo, stock inicial y ajustes formales, backfill/conciliación idempotente, feature flag de lectura y rollback. Mantén compatibilidad con products.stock, product_branch_stocks y product_warehouse_stocks durante la transición.

No implementes reservas, expiración, locking avanzado o no-stock-negativo de FASE 04. Ejecuta validaciones y genera FASE_03_CONTEXTO_CIERRE.md. No avances automáticamente a FASE 04.
```

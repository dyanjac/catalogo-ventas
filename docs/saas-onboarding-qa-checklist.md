# SaaS Onboarding QA Checklist

## Objetivo
Validar el flujo administrativo completo de onboarding SaaS:
- alta de organizacion en DEMO
- readiness para PRODUCCION
- activacion a PRODUCCION
- suspension del tenant
- reactivacion del tenant
- guardas operativas y recuperacion guiada

## Precondiciones
- usuario con rol `super_admin`
- modulo de seguridad habilitado
- base migrada
- roles base existentes, incluyendo `super_admin`

## Flujo 1: Alta rapida en DEMO
1. Ir a `Admin > Organizaciones SaaS > Nueva organizacion`.
2. Registrar una organizacion con `tax_id`, email comercial, admin inicial y sucursal principal.
3. Confirmar redireccion al detalle del tenant.

### Esperado
- la organizacion nace con `environment = demo`
- la organizacion queda con `status = active`
- existe sucursal principal activa y default
- existe admin inicial activo con rol `super_admin`
- existen `commerce_settings`, `billing_settings` y `accounting_settings`
- la cabecera admin muestra marca de entorno `DEMO`

## Flujo 2: Readiness para PRODUCCION
1. Abrir la ficha de la organizacion creada.
2. Revisar la seccion `Preparacion para produccion`.

### Esperado
- todos los checks aparecen en `OK` si el tenant fue provisionado con `tax_id`
- el boton `Activar en produccion` solo aparece cuando todos los checks estan completos

## Flujo 3: Activacion a PRODUCCION
1. Desde la ficha del tenant DEMO listo, pulsar `Activar en produccion`.

### Esperado
- `environment` cambia a `production`
- `status` permanece en `active`
- se registra metadata `production_activated_at`
- se registra auditoria `saas.organization.production_activated`
- la ficha muestra `PRODUCTION ACTIVO`

## Flujo 4: Guardas operativas en PRODUCCION
1. Intentar dejar vacio `tax_id`.
2. Intentar desactivar sucursal principal.
3. Intentar desactivar admin inicial.

### Esperado
- los tres intentos deben ser rechazados con error de validacion

## Flujo 5: Suspension del tenant
1. Desde la ficha del tenant, pulsar `Suspender tenant`.

### Esperado
- `status` cambia a `suspended`
- se registra metadata `suspended_at`
- se registra auditoria `saas.organization.suspended`
- la organizacion no cambia su `environment`

## Flujo 6: Reactivacion del tenant
1. Desde la ficha del tenant suspendido, pulsar `Reactivar tenant`.

### Esperado
- `status` vuelve a `active`
- se registra metadata `reactivated_at`
- se registra auditoria `saas.organization.reactivated`

## Flujo 7: Restriccion para organizacion default
1. Identificar una organizacion con `is_default = true`.
2. Intentar suspenderla desde la ficha.

### Esperado
- el boton debe estar deshabilitado o la accion debe ser rechazada
- el estado no debe cambiar a `suspended`

## Flujo 8: Recuperacion guiada
1. Eliminar o desactivar manualmente la sucursal principal en un tenant DEMO de prueba.
2. Abrir la ficha del tenant.
3. Usar `Reconstruir sucursal principal`.
4. Repetir el mismo ejercicio con el admin inicial.

### Esperado
- la UI muestra la seccion `Recuperacion guiada`
- la sucursal principal se recrea activa y default
- el admin inicial se recrea como `super_admin`
- al reconstruir el admin se muestran credenciales temporales

## Ejecucion automatizada
Existe una suite puntual en `tests/Feature/OrganizationOnboardingFlowTest.php`.

Comando:
```bash
php artisan test --filter=OrganizationOnboardingFlowTest
```

## Observacion de entorno actual
La ejecucion automatizada requiere `pdo_sqlite`, porque `phpunit.xml` usa `sqlite` en memoria. Si el entorno CLI no tiene ese driver, la suite no correra hasta habilitarlo.

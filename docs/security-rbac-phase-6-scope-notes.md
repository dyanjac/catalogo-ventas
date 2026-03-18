# Fase 6 - Alcances RBAC (`own`, `branch`, `all`)

## Estado actual

La fase 6 introduce enforcement real de alcance por usuario sobre consultas y accesos a registros en los modulos administrativos.

Actualmente se aplica en:

- `customers`
- `sales`
- `billing`

## Regla operativa

- `all`: acceso completo al conjunto de registros del modulo.
- `own`: acceso solo a registros propios del usuario autenticado.
- `branch`: temporalmente degradado a `own` en los modulos anteriores.

## Motivo de la degradacion temporal de `branch`

El proyecto todavia no dispone de una dimension transversal de sucursal, sede o almacen en los modelos principales usados por el panel:

- clientes
- pedidos
- comprobantes

No existe hoy una columna comun como `branch_id`, `store_id` u `office_id` que permita filtrar registros por sede de forma consistente.

## Implementacion actual

- Clientes:
  - `own` y `branch` solo pueden ver su propio usuario.
- Pedidos:
  - `own` y `branch` solo pueden ver pedidos cuyo `user_id` coincide con el usuario autenticado.
- Comprobantes:
  - `own` y `branch` solo pueden ver comprobantes cuyo pedido pertenece al usuario autenticado.

## Modulos sin alcance por registro

Por ahora catalogo y modulos similares siguen protegidos a nivel de modulo / permiso, pero no a nivel de fila porque no existe una relacion de pertenencia o sucursal lista para enforcement.

## Siguiente paso recomendado

Para soportar `branch` real se necesita introducir una dimension organizacional comun, por ejemplo:

- `branches`
- `branch_id` en usuarios, pedidos, comprobantes y entidades operativas
- mapeo de roles y usuarios a una o varias sucursales

Una vez exista esa dimension, `SecurityScopeService` puede pasar de degradar `branch` a filtrarlo realmente por sede.

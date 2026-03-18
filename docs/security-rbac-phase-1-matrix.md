# Fase 1: Matriz Inicial de Acceso por Roles

## Objetivo
Definir una matriz base de acceso para el módulo `Security` que permita controlar:

- qué módulos puede ver cada rol
- qué acciones puede ejecutar dentro de cada módulo
- qué módulos futuros deben quedar reservados desde ahora, aunque todavía no estén implementados

Esta fase no implementa todavía el RBAC en base de datos. Su objetivo es fijar el catálogo inicial de módulos, roles y permisos para que las siguientes fases se construyan sobre una convención estable.

## Principios

- Cada módulo debe tener un código estable y único.
- Cada permiso debe expresarse como `modulo.recurso.accion`.
- El acceso se evalúa en tres niveles:
  - acceso al módulo
  - acciones permitidas
  - alcance de datos
- Los módulos futuros deben existir desde ya en la matriz para que el menú y la autorización puedan contemplarlos como `en_construccion`.
- El campo `users.role` queda solo como compatibilidad temporal.
- La fuente futura de autorización será `security_user_roles + security_role_permissions + security_role_module_access`.

## Catálogo inicial de módulos

### Módulos operativos actuales

| Código | Módulo | Estado |
|---|---|---|
| `dashboard` | Dashboard ejecutivo | Implementado |
| `sales` | Ventas / pedidos | Implementado |
| `pos` | Punto de venta | Implementado |
| `customers` | Clientes | Implementado |
| `catalog` | Catálogo / productos | Implementado |
| `billing` | Facturación electrónica / comprobantes | Implementado |
| `accounting` | Contabilidad | Parcial |
| `commerce` | Configuración de comercio | Implementado |
| `admin_theme` | Paleta / tema admin | Implementado |
| `security` | Seguridad / autenticación / roles | Parcial |
| `orders_front` | Pedidos del ecommerce / mis pedidos | Implementado |

### Módulos futuros reservados

| Código | Módulo | Estado inicial |
|---|---|---|
| `imports` | Importaciones | En construcción |
| `finance` | Finanzas | En construcción |
| `crm` | CRM | En construcción |
| `reports` | Reportes | En construcción |
| `inventory` | Inventarios | En construcción |
| `warranties` | Garantías | En construcción |
| `payroll` | Planillas | En construcción |

## Acciones base por módulo

Estas acciones sirven como vocabulario común para todos los módulos.

| Acción | Descripción |
|---|---|
| `view` | Ver módulo, listados y paneles |
| `create` | Registrar nuevos elementos |
| `update` | Editar elementos existentes |
| `delete` | Eliminar elementos |
| `approve` | Aprobar, confirmar o autorizar |
| `configure` | Cambiar parámetros del módulo |
| `export` | Exportar información |
| `reprocess` | Reintentar o reprocesar operaciones |
| `assign` | Asignar usuarios, grupos, responsables o estados |
| `audit` | Ver trazabilidad, historial y auditoría |

## Alcances base

| Código | Descripción |
|---|---|
| `own` | Solo recursos propios |
| `branch` | Recursos de su sede o unidad |
| `all` | Acceso global |

## Roles iniciales del sistema

| Código | Rol | Objetivo |
|---|---|---|
| `super_admin` | Super administrador | Control total del sistema |
| `security_admin` | Administrador de seguridad | Gestión de acceso, roles, autenticación y auditoría |
| `general_manager` | Gerencia general | Visión global, consulta y aprobación estratégica |
| `sales_manager` | Jefe comercial | Supervisión de ventas, clientes y POS |
| `sales_cashier` | Cajero POS | Operación de caja y emisión diaria |
| `billing_manager` | Responsable de facturación | Gestión documental y contingencias |
| `catalog_manager` | Responsable de catálogo | Administración de productos y catálogos |
| `accounting_manager` | Responsable contable | Control contable y cierres |
| `warehouse_manager` | Responsable de inventario | Futuro control de stock e inventario |
| `crm_manager` | Responsable CRM | Futuro seguimiento comercial y clientes |
| `finance_manager` | Responsable de finanzas | Futuro control financiero |
| `hr_manager` | Responsable de planillas | Futuro control de personal y planillas |
| `support_agent` | Soporte / atención | Consulta operativa de clientes y pedidos |
| `customer` | Cliente ecommerce | Acceso al portal público y pedidos propios |

## Matriz inicial de acceso por módulos

Leyenda:

- `A`: acceso total al módulo
- `L`: acceso limitado
- `C`: solo consulta
- `-`: sin acceso
- `EC`: módulo en construcción visible solo como placeholder

| Rol | dashboard | sales | pos | customers | catalog | billing | accounting | commerce | admin_theme | security | orders_front | imports | finance | crm | reports | inventory | warranties | payroll |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `super_admin` | A | A | A | A | A | A | A | A | A | A | A | EC | EC | EC | EC | EC | EC | EC |
| `security_admin` | C | - | - | C | - | C | C | - | C | A | - | EC | EC | EC | C | EC | EC | EC |
| `general_manager` | C | C | C | C | C | C | C | C | - | C | C | EC | EC | EC | EC | EC | EC | EC |
| `sales_manager` | C | A | A | A | C | C | - | - | - | - | C | - | - | C | C | EC | - | - |
| `sales_cashier` | C | L | A | L | C | L | - | - | - | - | - | - | - | - | - | - | - | - |
| `billing_manager` | C | C | C | C | C | A | C | - | - | - | C | - | - | - | C | - | - | - |
| `catalog_manager` | C | C | - | C | A | C | - | - | - | - | - | - | - | - | C | EC | - | - |
| `accounting_manager` | C | C | - | C | C | C | A | - | - | - | - | - | EC | - | C | EC | - | - |
| `warehouse_manager` | C | C | C | - | C | - | - | - | - | - | - | EC | - | - | C | EC | EC | - |
| `crm_manager` | C | C | - | A | C | - | - | - | - | - | C | - | - | EC | C | - | - | - |
| `finance_manager` | C | C | - | C | C | C | C | - | - | - | - | - | EC | - | C | - | - | - |
| `hr_manager` | C | - | - | C | - | - | C | - | - | - | - | - | - | - | C | - | - | EC |
| `support_agent` | C | C | - | C | C | C | - | - | - | - | C | - | - | - | - | - | C | - |
| `customer` | - | - | - | - | C | - | - | - | - | - | L | - | - | - | - | - | - | - |

## Matriz inicial de permisos detallados por rol

### 1. `super_admin`

- Todos los módulos actuales: `view`, `create`, `update`, `delete`, `approve`, `configure`, `export`, `reprocess`, `assign`, `audit`
- Alcance: `all`
- Módulos futuros: acceso placeholder `view`

### 2. `security_admin`

- `security`: `view`, `create`, `update`, `assign`, `configure`, `audit`
- `dashboard`: `view`
- `customers`: `view`
- `billing`: `view`, `audit`
- `accounting`: `view`, `audit`
- `admin_theme`: `view`, `update`, `configure`
- `reports`: `view`
- Alcance: `all`

### 3. `general_manager`

- `dashboard`: `view`
- `sales`: `view`, `approve`, `export`
- `pos`: `view`
- `customers`: `view`
- `catalog`: `view`
- `billing`: `view`, `approve`, `export`, `audit`
- `accounting`: `view`, `approve`, `export`
- `commerce`: `view`
- `security`: `view`
- `orders_front`: `view`
- Módulos futuros: `view`
- Alcance: `all`

### 4. `sales_manager`

- `dashboard`: `view`
- `sales`: `view`, `create`, `update`, `approve`, `export`
- `pos`: `view`, `create`, `update`, `approve`
- `customers`: `view`, `create`, `update`
- `catalog`: `view`
- `billing`: `view`, `export`
- `crm`: `view`
- `reports`: `view`, `export`
- Alcance: `branch`

### 5. `sales_cashier`

- `dashboard`: `view`
- `sales`: `view`, `create`
- `pos`: `view`, `create`, `update`
- `customers`: `view`, `create`, `update`
- `catalog`: `view`
- `billing`: `view`, `create`
- Alcance: `own` o `branch` según sede

### 6. `billing_manager`

- `dashboard`: `view`
- `sales`: `view`
- `pos`: `view`
- `customers`: `view`
- `catalog`: `view`
- `billing`: `view`, `create`, `update`, `approve`, `export`, `reprocess`, `audit`
- `accounting`: `view`
- `orders_front`: `view`
- `reports`: `view`, `export`
- Alcance: `all`

### 7. `catalog_manager`

- `dashboard`: `view`
- `sales`: `view`
- `customers`: `view`
- `catalog`: `view`, `create`, `update`, `delete`, `export`
- `billing`: `view`
- `reports`: `view`, `export`
- `inventory`: placeholder `view`
- Alcance: `all`

### 8. `accounting_manager`

- `dashboard`: `view`
- `sales`: `view`
- `customers`: `view`
- `catalog`: `view`
- `billing`: `view`, `audit`, `export`
- `accounting`: `view`, `create`, `update`, `approve`, `export`, `audit`, `configure`
- `finance`: placeholder `view`
- `reports`: `view`, `export`
- `inventory`: placeholder `view`
- Alcance: `all`

### 9. `warehouse_manager`

- `dashboard`: `view`
- `sales`: `view`
- `pos`: `view`
- `catalog`: `view`, `update`
- `reports`: `view`
- `imports`: placeholder `view`
- `inventory`: placeholder `view`
- `warranties`: placeholder `view`
- Alcance: `branch`

### 10. `crm_manager`

- `dashboard`: `view`
- `sales`: `view`
- `customers`: `view`, `create`, `update`, `export`
- `catalog`: `view`
- `orders_front`: `view`
- `crm`: placeholder `view`
- `reports`: `view`, `export`
- Alcance: `all`

### 11. `finance_manager`

- `dashboard`: `view`
- `sales`: `view`
- `customers`: `view`
- `catalog`: `view`
- `billing`: `view`, `audit`, `export`
- `accounting`: `view`, `export`
- `finance`: placeholder `view`
- `reports`: `view`, `export`
- Alcance: `all`

### 12. `hr_manager`

- `dashboard`: `view`
- `customers`: `view`
- `accounting`: `view`
- `reports`: `view`
- `payroll`: placeholder `view`
- Alcance: `all`

### 13. `support_agent`

- `dashboard`: `view`
- `sales`: `view`
- `customers`: `view`, `update`
- `catalog`: `view`
- `billing`: `view`, `audit`
- `orders_front`: `view`
- `warranties`: placeholder `view`
- Alcance: `branch`

### 14. `customer`

- `catalog`: `view`
- `orders_front`: `view`, `create`
- Alcance: `own`

## Catálogo inicial de permisos por código

### Dashboard

- `dashboard.overview.view`

### Ventas

- `sales.orders.view`
- `sales.orders.create`
- `sales.orders.update`
- `sales.orders.delete`
- `sales.orders.approve`
- `sales.orders.export`

### POS

- `pos.sales.view`
- `pos.sales.create`
- `pos.sales.update`
- `pos.sales.approve`

### Clientes

- `customers.records.view`
- `customers.records.create`
- `customers.records.update`
- `customers.records.delete`
- `customers.records.export`

### Catálogo

- `catalog.products.view`
- `catalog.products.create`
- `catalog.products.update`
- `catalog.products.delete`
- `catalog.products.export`
- `catalog.categories.view`
- `catalog.categories.create`
- `catalog.categories.update`
- `catalog.unit_measures.view`
- `catalog.unit_measures.create`
- `catalog.unit_measures.update`

### Facturación

- `billing.documents.view`
- `billing.documents.create`
- `billing.documents.update`
- `billing.documents.approve`
- `billing.documents.export`
- `billing.documents.reprocess`
- `billing.documents.audit`

### Contabilidad

- `accounting.entries.view`
- `accounting.entries.create`
- `accounting.entries.update`
- `accounting.entries.approve`
- `accounting.entries.export`
- `accounting.settings.configure`
- `accounting.audit.view`

### Comercio

- `commerce.settings.view`
- `commerce.settings.update`
- `commerce.settings.configure`

### Tema admin

- `admin_theme.palette.view`
- `admin_theme.palette.update`
- `admin_theme.palette.configure`

### Seguridad

- `security.roles.view`
- `security.roles.create`
- `security.roles.update`
- `security.permissions.view`
- `security.permissions.assign`
- `security.auth.view`
- `security.auth.configure`
- `security.audit.view`

### Pedidos ecommerce

- `orders_front.orders.view`
- `orders_front.orders.create`

### Módulos futuros

- `imports.module.view`
- `finance.module.view`
- `crm.module.view`
- `reports.module.view`
- `inventory.module.view`
- `warranties.module.view`
- `payroll.module.view`

## Reglas para módulos futuros

Mientras no estén implementados:

- solo `super_admin`, `general_manager` y roles dueños del dominio podrán verlos en el menú
- la pantalla debe mostrar `Módulo en construcción`
- el acceso directo por URL debe pasar por `security.module_access`
- no deben aparecer acciones operativas todavía

## Criterios de implementación para Fase 2

La siguiente fase debe crear:

- `security_roles`
- `security_permissions`
- `security_role_permissions`
- `security_user_roles`
- `security_modules`
- `security_role_module_access`

Además:

- seeders iniciales con esta matriz
- servicio central de resolución de permisos
- middleware por módulo
- sidebar dinámico según permisos efectivos

## Decisiones abiertas

Estas decisiones deben cerrarse antes de Fase 2:

- si un usuario podrá tener múltiples roles activos simultáneamente
- si el alcance `branch` dependerá de sucursal, almacén o empresa
- si los módulos futuros se mostrarán por defecto a gerencia o solo a `super_admin`
- si LDAP podrá mapear grupos directamente a roles internos

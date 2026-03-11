# ERP Modular Domain Map

## Ownership Rules
- Cada tabla tiene un módulo dueño.
- El módulo dueño define Entities, Repositories, Services y migraciones.
- Otros módulos consumen ese dominio por Contracts/Services/Eventos.

## Table to Module Ownership
- Core: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `failed_jobs`, `job_batches`, `jobs`, `migrations`
- Catalog: `products`, `product_images`, `categories`, `unit_measures`
- Orders: `orders`, `order_items`
- Billing: `billing_settings`, `billing_documents`, `billing_document_files`
- ElectronicDocuments: `billing_document_response_histories`, `billing_sunat_operation_types`, `document_templates`
- Accounting: `accounting_accounts`, `accounting_entries`, `accounting_entry_lines`, `accounting_entry_attachments`, `accounting_cost_centers`, `accounting_periods`, `accounting_settings`, `accounting_audit_logs`
- Admin: `admin_settings`
- AdminTheme: `admin_theme_settings`
- Commerce: `commerce_settings`

## Compatibility Policy
- Durante la transición se permiten wrappers en `app/Models` que extiendan entidades de módulo.
- Se mantienen nombres de rutas actuales para evitar regresiones en frontend y tests.


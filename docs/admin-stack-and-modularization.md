# Admin Stack And Modularization (nwidart/laravel-modules)

## Current Status (Implemented)
- Package installed: `nwidart/laravel-modules` (`^12.0`).
- Composer merge enabled for module autoload:
  - `extra.merge-plugin.include = ["Modules/*/composer.json"]`
- Base modules created and enabled:
  - `Modules/Admin`
  - `Modules/Catalog`
  - `Modules/Orders`
  - `Modules/Core`
- Main web routes are now split by domain in module route files:
  - `Modules/Core/routes/web.php`
  - `Modules/Catalog/routes/web.php`
  - `Modules/Orders/routes/web.php`
  - `Modules/Admin/routes/web.php`
- `routes/web.php` remains as a lightweight entrypoint for compatibility.

## Route Boundaries
- `Core`:
  - Home, contacto, nosotros
  - Login/Register/Logout
- `Catalog`:
  - Public products, categories, catalog
  - Cart operations
- `Orders`:
  - Checkout
  - Customer order index/detail (`orders.mine`, `orders.show`)
- `Admin`:
  - Admin dashboard/settings
  - Admin products/categories/unit measures/customers/orders
  - Protected with `auth` + `EnsureSuperAdmin`

## Admin UI Stack (Frozen)
- AdminLTE `3.2.x`
- Bootstrap `4.6.x`
- jQuery `3.7.x`
- FontAwesome

Rule: do not introduce Bootstrap 5 admin classes.  
If needed, add compatibility only in `resources/css/admin.css`.

## Service/Branding Baseline
- `CommerceSettingsService` remains the runtime source for storefront branding data.
- Data priority remains:
  1. `commerce_settings` table
  2. `config/commerce.php` (`env` fallback)

## Seeds Required In New Environments
- `SuperAdminSeeder`
- `CommerceSettingSeeder`

## Incremental Migration Plan (Next)
1. Done: moved Admin controllers from `App\Http\Controllers\Admin/*` to `Modules/Admin/app/Http/Controllers`.
2. Done: moved Catalog/Cart controllers to `Modules/Catalog/app/Http/Controllers`.
3. Done: moved checkout/customer order controller to `Modules/Orders/app/Http/Controllers`.
4. Next: move shared services/middleware (e.g. `CommerceSettingsService`, `EnsureSuperAdmin`) into `Modules/Core` only after stable tests.
5. Next: add module-level tests (`Modules/*/tests`) and keep integration tests in `tests/Feature`.
6. Next: migrate view paths by module (`Modules/*/resources/views`) without changing route names.

## Commands
```bash
php artisan module:list
php artisan route:list
php artisan module:make <ModuleName>
composer dump-autoload
```

## Notes
- Keep route names stable to avoid UI regressions and broken links.
- Migrate code by module with a strangler pattern (routes first, then controllers/services/models).
- Avoid big-bang moves of Eloquent models until bounded contexts are validated.

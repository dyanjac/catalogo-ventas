# Admin Stack And Pre-Modularization Baseline

## Current Frontend Split
- Public ecommerce uses `resources/css/app.css` and `resources/js/app.js`.
- Admin CMS uses `resources/css/admin.css` and `resources/js/admin.js`.
- Both bundles are compiled through Vite in `vite.config.js`.

## Admin UI Stack (Frozen)
- AdminLTE `3.2.x`
- Bootstrap `4.6.x`
- jQuery `3.7.x`
- FontAwesome (npm package)

## Rule: Avoid New Bootstrap 5 Classes In Admin
Admin currently runs on Bootstrap 4 through AdminLTE 3.  
If a BS5 class is used accidentally, add compatibility only in `resources/css/admin.css`.

## Route Naming And Protection Conventions
- Public routes: `home`, `catalog.*`, `cart.*`, `checkout.*`, `orders.*`, `contacto.*`, `nosotros.*`
- Admin routes: `admin.*`
- Sensitive admin routes must stay under middleware:
  - `auth`
  - `EnsureSuperAdmin`

## Commerce Branding Source Of Truth
- Runtime view data comes from `CommerceSettingsService` (cached).
- Priority:
  1. `commerce_settings` table
  2. `config/commerce.php` from env values

## Seeds Required For New Environments
- `SuperAdminSeeder`
- `CommerceSettingSeeder`

## Suggested Modularization Order
1. `Admin`
2. `Catalog`
3. `Orders`
4. `Customers/Auth`
5. `Core/Shared`

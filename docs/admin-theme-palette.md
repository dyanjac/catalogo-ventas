# Paleta administrativa ? implementaci?n por fases

## Fase 1: configuraci?n y formulario

La fuente de verdad es Modules/AdminTheme/config/config.php. La paleta combina sidebar azul gris?ceo, superficies claras y acciones verde petr?leo. Admin y autenticaci?n consumen la misma configuraci?n. El fondo general usa grises neutros; no es un campo editable de la paleta.

Los valores base son #RRGGBB. card_border y focus_ring tambi?n aceptan #RRGGBBAA para mantener compatibilidad con personalizaciones existentes. El selector nativo muestra RGB y conserva el alfa del campo de texto. El foco aplica transparencia mediante CSS.

## Fase 2: datos e instalaciones

La migraci?n 2026_10_05_120000_refresh_default_admin_palette compara todos los campos con una copia inmutable de la paleta anterior. Reconoce may?sculas y campos null/vac?os como valores heredados. Si cualquier color efectivo difiere, conserva el registro completo. Una personalizaci?n id?ntica a la paleta antigua es indistinguible y se actualizar?.

La actualizaci?n es condicional a los valores le?dos para evitar sobrescribir una edici?n concurrente. Se invalidan las entradas de cach? v1 y v2 de los registros seleccionados; el servicio usa v2 para evitar valores v1 almacenados indefinidamente, incluso sin registro en base de datos.

El seeder y el aprovisionamiento usan firstOrCreate para preservar registros existentes. Restablecer elimina la personalizaci?n de la organizaci?n y vuelve a los nuevos valores base.

El rollback de esta migraci?n de datos conserva los colores. Sin historial de procedencia, revertirlos podr?a sobrescribir una elecci?n posterior del administrador.

## Fase 3: verificaci?n y despliegue

Pruebas: php artisan test --filter=AdminThemePalette. Cubren guardado, rechazo de alfa en campos opacos, transparencia heredada, restablecimiento, cach?, aislamiento, migraci?n selectiva e idempotente, y conservaci?n al repetir el seeder.

Build: npm run build con Node 20.19+ o 22.12+. En despliegue, ejecutar php artisan migrate --force y regenerar la cach? de configuraci?n seg?n el procedimiento habitual (php artisan config:cache). Publicar los assets del build junto con el c?digo.

Revisar visualmente panel, login, foco por teclado y PDFs de comprobantes que consuman palette_primary/palette_border. El servicio de PDF ya resuelve la paleta desde AdminTheme; no requiere cambios de esquema.

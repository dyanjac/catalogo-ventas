<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\UnitMeasure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportProductsFromText extends Command
{
    protected $signature = 'products:import-text';
    protected $description = 'Importa productos desde un bloque de texto tab-separated embebido en el comando';

    private const DATA = <<<'TSV'
UNIDADMEDIDA	CATEGORIA	PRECIO	PRECIO COMPRA	PRECIO VENTA MAYOR	PRECIO PROMEDIO	STOCK	STOCK MINIMO	TIPO AFECTACION	USA SERIE	CUENTA
SACO	HARINA DE TRIGO	128.50	110.00	122.00	119.50	25	10	Gravado	N	701101
BOLSA	AZUCAR RUBIA	88.90	76.10	84.00	82.50	40	12	Gravado	S	701102
UNIDAD	ESENCIA VAINILLA	12.50	8.50	10.80	10.20	15	5	Exonerado	N	701201
CAJA	MANTECA	145.00	130.00	139.00	137.50	8	4	Gravado	N	701103
PAQUETE	PANETONERA	18.90			17.50	20	6	Inafecto	N	
TSV;

    public function handle(): int
    {
        $lines = preg_split('/\r\n|\n|\r/', trim(self::DATA)) ?: [];

        if (count($lines) <= 1) {
            $this->warn('No hay data para importar.');
            return self::SUCCESS;
        }

        $rows = array_slice($lines, 1);
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $index => $line) {
                if (trim($line) === '') {
                    continue;
                }

                $cols = str_getcsv($line, "\t");
                $unitName = $this->normalizeText($cols[0] ?? null);
                $categoryName = $this->normalizeText($cols[1] ?? null);

                if (! $unitName || ! $categoryName) {
                    continue;
                }

                $category = Category::firstOrCreate(
                    ['name' => Str::upper($categoryName)],
                    ['slug' => Str::slug($categoryName)]
                );

                $unit = UnitMeasure::firstOrCreate(['name' => Str::upper($unitName)]);
                $name = Str::upper($categoryName);

                $existing = Product::where('name', $name)
                    ->where('category_id', $category->id)
                    ->where('unit_measure_id', $unit->id)
                    ->first();

                $payload = [
                    'category_id' => $category->id,
                    'unit_measure_id' => $unit->id,
                    'name' => $name,
                    'sku' => $existing?->sku ?: $this->makeSku($name),
                    'slug' => $existing?->slug ?: $this->makeUniqueSlug($name),
                    'tax_affectation' => $this->normalizeText($cols[8] ?? null) ?? 'Gravado',
                    'uses_series' => $this->toBoolean($cols[9] ?? null),
                    'account' => $this->normalizeText($cols[10] ?? null),
                    'purchase_price' => $this->toDecimal($cols[3] ?? null),
                    'sale_price' => $this->toDecimal($cols[2] ?? null),
                    'wholesale_price' => $this->toDecimal($cols[4] ?? null),
                    'average_price' => $this->toDecimal($cols[5] ?? null),
                    'price' => $this->toDecimal($cols[2] ?? null) ?? 0,
                    'stock' => $this->toInt($cols[6] ?? null),
                    'min_stock' => $this->toInt($cols[7] ?? null),
                    'is_active' => true,
                ];

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    Product::create($payload);
                    $created++;
                }

                $this->line('Procesado: fila ' . ($index + 2) . ' - ' . $name);
            }
        });

        $this->info("Importación finalizada. Creados: {$created}, actualizados: {$updated}");

        return self::SUCCESS;
    }

    private function toBoolean(mixed $value): bool
    {
        return Str::upper((string) $value) === 'S';
    }

    private function normalizeText(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function toInt(mixed $value): int
    {
        $value = $this->normalizeText($value);
        return $value === null ? 0 : max(0, (int) $value);
    }

    private function toDecimal(mixed $value): ?float
    {
        $value = $this->normalizeText($value);

        if ($value === null) {
            return null;
        }

        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function makeSku(string $name): string
    {
        $prefix = Str::upper(Str::substr(Str::slug($name, ''), 0, 4));
        $prefix = $prefix !== '' ? $prefix : 'PRD';

        do {
            $sku = "{$prefix}-" . Str::upper(Str::random(6));
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }

    private function makeUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : Str::lower(Str::random(8));
        $slug = $base;
        $counter = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}

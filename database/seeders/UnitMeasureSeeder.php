<?php

namespace Database\Seeders;

use App\Models\UnitMeasure;
use Illuminate\Database\Seeder;

class UnitMeasureSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['SACO', 'BOLSA', 'PAQUETE', 'UNIDAD', 'CAJA', 'BALDE', 'BIDON', 'BARRA'] as $name) {
            UnitMeasure::firstOrCreate(['name' => $name]);
        }
    }
}

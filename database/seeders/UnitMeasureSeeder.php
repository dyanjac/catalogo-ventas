<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;
use Modules\Catalog\Entities\UnitMeasure;

class UnitMeasureSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->where('is_default', true)->first()
            ?? Organization::query()->orderBy('id')->first();

        if (! $organization) {
            return;
        }

        foreach (['SACO', 'BOLSA', 'PAQUETE', 'UNIDAD', 'CAJA', 'BALDE', 'BIDON', 'BARRA'] as $name) {
            UnitMeasure::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => $name,
                ],
                []
            );
        }
    }
}

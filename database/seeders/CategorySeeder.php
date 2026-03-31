<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Catalog\Entities\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->where('is_default', true)->first()
            ?? Organization::query()->orderBy('id')->first();

        if (! $organization) {
            return;
        }

        foreach ([
            ['name' => 'Electronica', 'slug' => 'electronica'],
            ['name' => 'Hogar', 'slug' => 'hogar'],
            ['name' => 'Ropa', 'slug' => 'ropa'],
        ] as $category) {
            Category::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'slug' => Str::slug($category['slug']),
                ],
                [
                    'name' => $category['name'],
                    'description' => null,
                ]
            );
        }
    }
}

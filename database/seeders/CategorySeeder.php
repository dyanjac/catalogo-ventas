<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void {
        foreach ([
            ['name'=>'Electrónica','slug'=>'electronica'],
            ['name'=>'Hogar','slug'=>'hogar'],
            ['name'=>'Ropa','slug'=>'ropa'],
        ] as $c) {
            Category::firstOrCreate(['slug'=>$c['slug']], $c);
        }
    }
}

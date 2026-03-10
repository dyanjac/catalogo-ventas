<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('billing_sunat_operation_types')) {
            Schema::create('billing_sunat_operation_types', function (Blueprint $table) {
                $table->id();
                $table->string('code', 2)->unique();
                $table->string('description', 160);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        $now = now();
        $rows = [
            ['code' => '01', 'description' => 'Venta Interna', 'sort_order' => 10],
            ['code' => '02', 'description' => 'Exportación', 'sort_order' => 20],
            ['code' => '03', 'description' => 'No Domiciliados', 'sort_order' => 30],
            ['code' => '04', 'description' => 'Venta Interna - Anticipos', 'sort_order' => 40],
            ['code' => '05', 'description' => 'Venta Itinerante', 'sort_order' => 50],
            ['code' => '06', 'description' => 'Factura Guía', 'sort_order' => 60],
            ['code' => '07', 'description' => 'Venta Arroz Pilado', 'sort_order' => 70],
            ['code' => '08', 'description' => 'Factura - Comprobante de Percepción', 'sort_order' => 80],
            ['code' => '10', 'description' => 'Factura - Guía remitente', 'sort_order' => 90],
            ['code' => '11', 'description' => 'Factura - Guía transportista', 'sort_order' => 100],
            ['code' => '12', 'description' => 'Boleta de venta - Comprobante de Percepción', 'sort_order' => 110],
            ['code' => '13', 'description' => 'Gasto Deducible Persona Natural', 'sort_order' => 120],
        ];

        foreach ($rows as $row) {
            DB::table('billing_sunat_operation_types')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'description' => $row['description'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_sunat_operation_types');
    }
};


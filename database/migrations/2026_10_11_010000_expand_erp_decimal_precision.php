<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each entry is [scale, nullable, has_default]. Existing integer values are
     * widened exactly; no data is rounded during this migration.
     *
     * @var array<string, array<string, array{int, bool, bool}>>
     */
    private array $columns = [
        'products' => [
            'price' => [6, false, false],
            'purchase_price' => [6, true, false],
            'sale_price' => [6, true, false],
            'wholesale_price' => [6, true, false],
            'average_price' => [6, true, false],
            'stock' => [4, false, true],
            'min_stock' => [4, false, true],
        ],
        'product_branch_stocks' => [
            'stock' => [4, false, true],
            'min_stock' => [4, false, true],
        ],
        'product_warehouse_stocks' => [
            'stock' => [4, false, true],
            'min_stock' => [4, false, true],
            'average_cost' => [6, false, true],
            'last_cost' => [6, false, true],
        ],
        'inventory_balances' => [
            'physical_stock' => [4, false, true],
            'reserved_stock' => [4, false, true],
            'in_transit_stock' => [4, false, true],
            'min_stock' => [4, false, true],
            'average_cost' => [6, false, true],
            'last_cost' => [6, false, true],
        ],
        'inventory_movements' => [
            'quantity' => [4, false, false],
            'stock_before' => [4, false, false],
            'stock_after' => [4, false, false],
            'average_cost_before' => [6, false, true],
            'unit_cost' => [6, false, true],
            'average_cost_after' => [6, false, true],
            'total_cost' => [6, false, true],
        ],
        'inventory_reconciliation_issues' => [
            'expected_value' => [6, true, false],
            'actual_value' => [6, true, false],
        ],
        'inventory_document_items' => [
            'quantity' => [4, false, false],
            'target_quantity' => [4, true, false],
            'unit_cost' => [6, true, false],
            'line_total' => [6, true, false],
        ],
        'inventory_transfer_items' => [
            'quantity' => [4, false, false],
            'dispatched_quantity' => [4, false, true],
            'received_quantity' => [4, false, true],
            'unit_cost' => [6, false, true],
        ],
        'inventory_transfer_event_items' => [
            'quantity' => [4, false, false],
            'transit_delta' => [4, false, true],
        ],
        'inventory_reservation_items' => [
            'quantity' => [4, false, false],
        ],
        'inventory_reservation_events' => [
            'quantity_delta' => [4, false, false],
        ],
        'order_items' => [
            'quantity' => [4, false, false],
            'reserved_quantity' => [4, false, true],
            'dispatched_quantity' => [4, false, true],
            'returned_quantity' => [4, false, true],
            'unit_price' => [6, false, false],
            'discount_amount' => [2, false, true],
            'tax_amount' => [2, false, true],
            'line_total' => [2, false, false],
        ],
        'orders' => [
            'subtotal' => [2, false, true],
            'discount' => [2, false, true],
            'shipping' => [2, false, true],
            'tax' => [2, false, true],
            'total' => [2, false, true],
        ],
        'billing_documents' => [
            'subtotal' => [2, false, true],
            'tax' => [2, false, true],
            'total' => [2, false, true],
        ],
        'accounting_entries' => [
            'total_debit' => [2, false, true],
            'total_credit' => [2, false, true],
        ],
        'accounting_entry_lines' => [
            'debit' => [2, false, true],
            'credit' => [2, false, true],
        ],
        'transport_guide_items' => [
            'quantity' => [4, false, false],
        ],
    ];

    public function up(): void
    {
        // SQLite rebuilds tables for type changes. Triggers referencing a table
        // being rebuilt must be restored after every table has its final name.
        $triggers = DB::getDriverName() === 'sqlite'
            ? DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'trigger' AND sql IS NOT NULL")
            : [];

        foreach ($triggers as $trigger) {
            DB::statement('DROP TRIGGER IF EXISTS "'.str_replace('"', '""', $trigger->name).'"');
        }

        try {
            foreach ($this->columns as $tableName => $columns) {
                if (! Schema::hasTable($tableName)) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($columns, $tableName): void {
                    foreach ($columns as $name => [$scale, $nullable, $hasDefault]) {
                        if (! Schema::hasColumn($tableName, $name)) {
                            continue;
                        }

                        $column = $table->decimal($name, 18, $scale);
                        if ($nullable) {
                            $column->nullable();
                        }
                        if ($hasDefault) {
                            $column->default(0);
                        }
                        $column->change();
                    }
                });
            }
        } finally {
            foreach ($triggers as $trigger) {
                DB::unprepared($trigger->sql);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Esta migracion no puede revertirse sin perder cantidades o precios decimales. Restaure una copia de seguridad verificada.');
    }
};

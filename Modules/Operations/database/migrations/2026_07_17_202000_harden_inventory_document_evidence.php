<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER inventory_document_items_confirmed_insert_guard BEFORE INSERT ON inventory_document_items WHEN EXISTS (SELECT 1 FROM inventory_documents d WHERE d.id = NEW.document_id AND d.status = 'confirmed') BEGIN SELECT RAISE(ABORT, 'confirmed inventory document items are immutable'); END");
        } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER inventory_document_items_confirmed_insert_guard BEFORE INSERT ON inventory_document_items FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM inventory_documents d WHERE d.id = NEW.document_id AND d.status = 'confirmed') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'confirmed inventory document items are immutable'; END IF; END");
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_document_items_confirmed_insert_guard');
    }
};

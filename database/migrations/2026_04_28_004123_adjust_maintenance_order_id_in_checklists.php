<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Alteração direta via SQL para garantir que não haverá erro de "coluna não existe"
        // Esta instrução remove a restrição NOT NULL da coluna maintenance_order_id
        DB::statement('ALTER TABLE maintenance_order_checklists ALTER COLUMN maintenance_order_id DROP NOT NULL');
    }

    public function down(): void
    {
        // Se precisar reverter, volta a ser NOT NULL
        DB::statement('ALTER TABLE maintenance_order_checklists ALTER COLUMN maintenance_order_id SET NOT NULL');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // capacity_value nasceu como string (2026_05_04_232911) e nunca foi
        // exposta no form/filtro do AssetResource — na prática está vazia em
        // producao (só o AssetFactory de teste popula), então o cast direto
        // é seguro. USING garante que qualquer valor residual não numérico
        // vire NULL em vez de quebrar a migration.
        if (Schema::hasColumn('assets', 'capacity_value')) {
            DB::statement("ALTER TABLE assets ALTER COLUMN capacity_value TYPE numeric(12,2) USING NULLIF(regexp_replace(capacity_value, '[^0-9.]', '', 'g'), '')::numeric");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('assets', 'capacity_value')) {
            DB::statement('ALTER TABLE assets ALTER COLUMN capacity_value TYPE varchar(255) USING capacity_value::varchar');
        }
    }
};

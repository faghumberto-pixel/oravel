<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Mesmo ajuste ja feito em subject_id nesta tabela (migration
        // 2026_05_04_021744): causer_id era bigint, mas User.id (que causa a
        // maioria dos eventos logados) e uuid. Sem isso, qualquer create/update
        // de um model com LogsActivity (ex: Asset) feito por um usuario logado
        // quebra com "invalid input syntax for type bigint".
        DB::statement('ALTER TABLE activity_log ALTER COLUMN causer_id TYPE VARCHAR(255) USING causer_id::varchar');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE activity_log ALTER COLUMN causer_id TYPE BIGINT USING causer_id::bigint');
    }
};

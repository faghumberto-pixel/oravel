<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // O PostgreSQL exige um cast explícito quando mudamos de bigint para varchar
        DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE VARCHAR(255) USING subject_id::text');
    }

    public function down(): void
    {
        // Caso precise reverter (opcional, mas recomendado)
        DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE BIGINT USING subject_id::bigint');
    }
};
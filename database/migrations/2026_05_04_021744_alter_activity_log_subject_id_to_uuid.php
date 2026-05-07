<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Alteramos a coluna subject_id para aceitar UUIDs (varchar 36)
        // Isso resolve o conflito de tipo de dado com o PostgreSQL
        DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE VARCHAR(36) USING subject_id::varchar');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverte para BIGINT se precisar desfazer
        DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE BIGINT USING subject_id::bigint');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->jsonb('checklist')->nullable()->after('status');
        });

        // Índice GIN para buscas rápidas dentro do JSONB no PostgreSQL
        DB::statement('CREATE INDEX assets_checklist_gin ON assets USING gin (checklist)');
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('checklist');
        });
    }
};

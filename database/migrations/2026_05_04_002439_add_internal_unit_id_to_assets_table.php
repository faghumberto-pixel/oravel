<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // INCLUSÃO MÍNIMA: Vincula o ativo a uma base/unidade interna
            $table->foreignUuid('internal_unit_id')
                ->nullable()
                ->after('client_id')
                ->constrained('internal_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['internal_unit_id']);
            $table->dropColumn('internal_unit_id');
        });
    }
};
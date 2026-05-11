<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Verifica se a coluna NÃO existe antes de tentar criar
            if (!Schema::hasColumn('assets', 'asset_tag')) {
                $table->string('asset_tag')->nullable()->after('patrimonio');
            }
            if (!Schema::hasColumn('assets', 'serial_number')) {
                $table->string('serial_number')->nullable()->after('asset_tag');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['asset_tag', 'serial_number']);
        });
    }
};
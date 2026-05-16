<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_status', function (Blueprint $blueprint) {
            // Criamos a coluna tenant_id para que o Filament saiba filtrar por empresa
            // Usamos foreignUuid porque o seu sistema usa UUIDs (como visto no erro 500 anterior)
            $blueprint->foreignUuid('tenant_id')
                ->nullable() // nullable primeiro para não dar erro em dados existentes
                ->constrained('tenants') 
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_status', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['tenant_id']);
            $blueprint->dropColumn('tenant_id');
        });
    }
};
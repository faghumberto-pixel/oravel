<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('checklist_groups', function (Blueprint $table) {
            // Verifica se a coluna NÃO existe antes de tentar criá-la
            if (!Schema::hasColumn('checklist_groups', 'tenant_id')) {
                $table->foreignId('tenant_id')
                    ->nullable() 
                    ->after('id') 
                    ->constrained()
                    ->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checklist_groups', function (Blueprint $table) {
            if (Schema::hasColumn('checklist_groups', 'tenant_id')) {
                $table->dropConstrainedForeignId('tenant_id');
            }
        });
    }
};
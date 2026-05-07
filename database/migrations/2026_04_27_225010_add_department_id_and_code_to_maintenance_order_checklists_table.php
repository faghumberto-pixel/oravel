<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Remove a coluna se ela existir (para garantir que removeremos qualquer 'bigint' incorreto)
        Schema::table('maintenance_order_checklists', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_order_checklists', 'department_id')) {
                // Remove a chave estrangeira se ela existir
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            }
            if (Schema::hasColumn('maintenance_order_checklists', 'code')) {
                $table->dropColumn('code');
            }
        });

        // 2. Recria a coluna com o tipo UUID correto
        Schema::table('maintenance_order_checklists', function (Blueprint $table) {
            $table->uuid('department_id')->nullable();
            $table->string('code', 20)->nullable();
            
            $table->foreign('department_id')
                  ->references('id')
                  ->on('departments')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_order_checklists', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'code']);
        });
    }
};
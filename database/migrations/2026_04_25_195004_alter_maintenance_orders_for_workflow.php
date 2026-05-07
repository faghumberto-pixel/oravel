<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            // Removemos a coluna caso ela tenha sido criada com o tipo errado na tentativa anterior
            if (Schema::hasColumn('maintenance_orders', 'assigned_technician_id')) {
                $table->dropColumn('assigned_technician_id');
            }
            if (Schema::hasColumn('maintenance_orders', 'parent_id')) {
                $table->dropColumn('parent_id');
            }

            // Criamos novamente com os tipos compatíveis
            $table->uuid('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('maintenance_orders')->onDelete('cascade');
            
            // foreignId usa bigint para alinhar com a tabela users padrão do Laravel
            $table->foreignId('assigned_technician_id')->nullable()->constrained('users');
            
            $table->dateTime('scheduled_at')->nullable();
            $table->string('workflow_status')->default('scheduled');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['assigned_technician_id']);
            $table->dropColumn(['parent_id', 'assigned_technician_id', 'scheduled_at', 'workflow_status']);
        });
    }
};
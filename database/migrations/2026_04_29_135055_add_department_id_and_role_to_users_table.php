<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Verifica manualmente se a coluna existe antes de tentar criar
            if (!Schema::hasColumn('users', 'department_id')) {
                $table->foreignUuid('department_id')
                    ->nullable()
                    ->constrained('departments')
                    ->onDelete('set null');
            }

            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('tecnico');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove as colunas apenas se elas existirem
            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            }
            
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
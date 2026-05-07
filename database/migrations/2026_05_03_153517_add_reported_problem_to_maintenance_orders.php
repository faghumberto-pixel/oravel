<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Só tenta criar se a tabela realmente não existir
        if (!Schema::hasTable('reported_problems')) {
            Schema::create('reported_problems', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id')->index();
                $table->string('description');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::table('maintenance_orders', function (Blueprint $table) {
            // Só adiciona a coluna se ela não existir
            if (!Schema::hasColumn('maintenance_orders', 'reported_problem_id')) {
                $table->uuid('reported_problem_id')->nullable();
                $table->foreign('reported_problem_id')->references('id')->on('reported_problems');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_orders', 'reported_problem_id')) {
                $table->dropForeign(['reported_problem_id']);
                $table->dropColumn('reported_problem_id');
            }
        });
        Schema::dropIfExists('reported_problems');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Utilizamos Schema::hasColumn para evitar o erro de coluna duplicada
            if (!Schema::hasColumn('assets', 'acquisition_date')) {
                $table->date('acquisition_date')->nullable();
            }
            if (!Schema::hasColumn('assets', 'acquisition_value')) {
                $table->decimal('acquisition_value', 15, 2)->nullable();
            }
            if (!Schema::hasColumn('assets', 'residual_value')) {
                $table->decimal('residual_value', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('assets', 'useful_life_years')) {
                $table->integer('useful_life_years')->default(10);
            }
            if (!Schema::hasColumn('assets', 'manual_items')) {
                $table->json('manual_items')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'acquisition_date',
                'acquisition_value',
                'residual_value',
                'useful_life_years',
                'manual_items'
            ]);
        });
    }
};
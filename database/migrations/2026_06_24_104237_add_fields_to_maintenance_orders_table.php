<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->foreignId('abc_matrix_id')->nullable()->after('priority')->constrained('abc_matrices')->onDelete('set null');
            $table->string('natureza_servico')->nullable()->comment('Interno ou Externo/Alugado')->after('abc_matrix_id');
            $table->integer('horimetro_anterior')->nullable()->after('natureza_servico');
            $table->integer('horimetro_atual')->nullable()->after('horimetro_anterior');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropForeignIdFor('abc_matrix_id');
            $table->dropColumn(['abc_matrix_id', 'natureza_servico', 'horimetro_anterior', 'horimetro_atual']);
        });
    }
};

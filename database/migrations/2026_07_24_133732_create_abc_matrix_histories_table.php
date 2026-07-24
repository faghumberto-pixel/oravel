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
        Schema::create('abc_matrix_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('nivel_anterior')->nullable();
            $table->string('nivel_novo');
            $table->foreignUuid('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Precisao 6 (microssegundos) de proposito -- timestamp() sem
            // precisao trunca pro segundo, e 2 mudancas de nivel no mesmo
            // segundo (comum em teste, possivel em uso real) empatam na
            // ordenacao cronologica do Historico do Patrimonio.
            $table->timestamp('changed_at', 6);
            $table->timestamps();

            $table->index(['tenant_id', 'asset_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abc_matrix_histories');
    }
};

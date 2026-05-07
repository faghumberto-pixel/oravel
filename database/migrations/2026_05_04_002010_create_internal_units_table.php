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
        Schema::create('internal_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            
            // Dados da Unidade
            $table->string('name'); 
            $table->string('code')->nullable(); // Ex: MATRIZ, FILIAL-01
            $table->boolean('is_active')->default(true);

            // Localização (Essencial para cálculo de KM futuro)
            $table->string('zip_code')->nullable(); // CEP
            $table->string('address')->nullable();
            $table->string('number')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable(); // UF

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_units');
    }
};
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
        Schema::create('asset_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            
            // Relacionamentos
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('maintenance_order_id')->nullable()->constrained()->nullOnDelete();
            
            // Rastro de Localização
            $table->string('from_location')->nullable()->comment('Origem (Nome da Unidade ou Cliente)');
            $table->string('to_location')->nullable()->comment('Destino (Nome da Unidade ou Cliente)');
            
            // Motivação
            $table->string('reason')->nullable(); // Ex: "Abertura de OS Externa", "Retorno de Manutenção"
            $table->timestamp('moved_at')->useCurrent();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_movements');
    }
};
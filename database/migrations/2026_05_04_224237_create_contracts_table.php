<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Se a tabela não existir, cria. Se existir, não faz nada.
        if (!Schema::hasTable('contracts')) {
            Schema::create('contracts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                
                // Alterado para foreignUuid para ser compatível com a tabela Tenants
                $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
                
                $table->foreignUuid('client_id')->constrained();
                $table->foreignUuid('asset_id')->constrained();
                
                $table->string('contract_number')->unique();
                $table->string('status')->default('Draft');
                $table->date('start_date');
                $table->decimal('price', 12, 2)->default(0);
                
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
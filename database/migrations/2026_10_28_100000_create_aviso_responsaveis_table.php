<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aviso_responsaveis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('evento');
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'evento', 'user_id']);
            $table->index(['tenant_id', 'evento']);
        });

        // Propostas: usuário escolhido para revisar (nome da pessoa, não o departamento).
        Schema::table('proposta_comerciais', function (Blueprint $table) {
            $table->foreignUuid('enviada_para_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proposta_comerciais', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enviada_para_user_id');
        });

        Schema::dropIfExists('aviso_responsaveis');
    }
};

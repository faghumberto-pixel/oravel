<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log de navegacao/acoes dos usuarios (tela por tela) -- diferente do
     * activity_log (Spatie), que so registra created/updated/deleted em
     * Asset/EquipmentDamage e nao tem conceito de "pagina visitada" nem
     * tenant_id. "Tempo na tela" nao e armazenado aqui -- e calculado na
     * tela de listagem como a diferenca entre um registro e o proximo do
     * mesmo usuario.
     */
    public function up(): void
    {
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('method', 10);
            $table->string('path');
            $table->string('route_name')->nullable();
            $table->string('resource_label')->nullable();
            $table->string('action')->nullable();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};

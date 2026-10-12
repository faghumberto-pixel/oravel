<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colab_fila_processada', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->uuid('user_id')->index();
            $table->uuid('acao_uuid'); // gerado no celular: garante que reenviar a mesma ação não aplica duas vezes
            $table->string('tipo', 40);
            $table->string('status', 20); // ok | ignorada | erro
            $table->string('mensagem')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'acao_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colab_fila_processada');
    }
};

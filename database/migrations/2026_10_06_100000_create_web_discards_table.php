<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contador DIARIO do que o coletor do site institucional descarta (robo, IP de servidor,
        // origem invalida). So numeros agregados -- nenhum dado pessoal -- para conferir a Central
        // com o Google Analytics sem adivinhar.
        Schema::create('web_discards', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('reason', 40);
            $table->unsignedInteger('hits')->default(0);      // paginas abertas (eventos pv) descartadas
            $table->unsignedInteger('sessions')->default(0);  // sessoes distintas descartadas
            $table->timestamps();
            $table->unique(['day', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_discards');
    }
};

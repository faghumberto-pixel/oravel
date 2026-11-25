<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Passo a passo em pop-up dentro de cada tela (config/tour.php): quais telas cada usuário já viu. Um registro por usuário e tour.
     * `passos_vistos` guarda as chaves das telas cujo pop-up já abriu sozinho; `dispensado_em` = a pessoa pediu para não abrir mais sozinho
     * (o botão "Passo a passo" continua funcionando).
     */
    public function up(): void
    {
        Schema::create('user_tours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tour', 40);
            $table->json('passos_vistos')->nullable();
            $table->timestamp('dispensado_em')->nullable();
            $table->unsignedSmallInteger('reaberturas')->default(0);   // vezes que abriu de propósito pelo botão
            $table->timestamps();
            $table->unique(['user_id', 'tour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tours');
    }
};

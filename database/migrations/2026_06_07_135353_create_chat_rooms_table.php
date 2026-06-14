<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Se a tabela já existir por erro anterior, vamos garantir que ela seja recriada corretamente
        Schema::dropIfExists('chat_rooms');

        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID como chave primária
            $table->string('type')->default('pessoal');
            $table->uuid('tenant_id'); // Relação multitenant
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_rooms');
    }
};
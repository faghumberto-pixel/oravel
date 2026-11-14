<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $t) {
            $t->text('mensagem_personalizada')->nullable()->after('observacoes');
            $t->text('o_que_olhar')->nullable()->after('mensagem_personalizada');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $t) {
            $t->dropColumn(['mensagem_personalizada', 'o_que_olhar']);
        });
    }
};

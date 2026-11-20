<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quem cancelou a inscricao na Gazeta (Sender) nao pode ser tratado como "aceitou receber".
        Schema::table('rel_contatos', function (Blueprint $t) {
            $t->timestamp('descadastrado_em')->nullable()->after('consentimento_origem');
        });
    }

    public function down(): void
    {
        Schema::table('rel_contatos', function (Blueprint $t) {
            $t->dropColumn('descadastrado_em');
        });
    }
};

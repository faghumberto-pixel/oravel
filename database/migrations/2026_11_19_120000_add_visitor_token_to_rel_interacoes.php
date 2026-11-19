<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Acesso anonimo (ferramenta/download do oravel.org) fica guardado com o token do navegador; quando a
        // pessoa se identifica, as interacoes anteriores desse token passam a ser dela (ver RegistraInteracao).
        Schema::table('rel_interacoes', function (Blueprint $t) {
            $t->string('visitor_token', 64)->nullable()->index()->after('dedupe_key');
        });
    }

    public function down(): void
    {
        Schema::table('rel_interacoes', function (Blueprint $t) {
            $t->dropColumn('visitor_token');
        });
    }
};

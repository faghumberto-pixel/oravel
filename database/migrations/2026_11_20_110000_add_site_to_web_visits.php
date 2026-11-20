<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // De qual site veio a visita. Tudo que ja existe e' do institucional (oravel.com.br): o default cobre o historico,
        // sem reescrever linha nenhuma. As visitas do oravel.org entram com site = 'oravel.org'.
        Schema::table('web_visits', function (Blueprint $t) {
            $t->string('site', 40)->default('oravel.com.br')->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('web_visits', function (Blueprint $t) {
            $t->dropColumn('site');
        });
    }
};

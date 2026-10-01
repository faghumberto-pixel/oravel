<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // O ip-api.com devolve o CÓDIGO da região: 2 letras no Brasil (SP), mas
        // até 3+ fora (ENG, NSW...). Com varchar(2), visitas do exterior davam
        // "value too long" e nem eram gravadas (achado no log de PROD 2026-10-01).
        Schema::table('site_visits', function (Blueprint $table) {
            $table->string('state', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sem reduzir de volta: encolher a coluna falharia se já houver região longa gravada.
    }
};

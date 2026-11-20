<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // IPs (ou faixas CIDR) cujas visitas NAO entram nas estatisticas dos sites da Oravel: a propria equipe e a rede da empresa.
        Schema::create('web_ips_ignorados', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('ip', 64)->unique(); // IP unico ou faixa CIDR (ex.: 200.10.20.0/24)
            $t->string('rotulo', 120)->nullable(); // "Meu IP", "Escritorio"...
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_ips_ignorados');
    }
};

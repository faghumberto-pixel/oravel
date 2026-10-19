<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Produtos contratados (lista de códigos de config/pacotes.php). Nulo = só o pacote principal (como sempre foi). */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('pacotes')->nullable()->after('pacote');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('pacotes');
        });
    }
};

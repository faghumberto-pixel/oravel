<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Quando os dados de exemplo de Suprimentos foram oferecidos (uma vez só; somem 30 dias depois, como os do Intelligence geral). */
    public function up(): void
    {
        Schema::table('intelligence_trials', function (Blueprint $table) {
            $table->timestamp('exemplo_suprimentos_oferecido_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_trials', function (Blueprint $table) {
            $table->dropColumn('exemplo_suprimentos_oferecido_em');
        });
    }
};

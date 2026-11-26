<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Senha escolhida no cadastro, guardada SÓ como hash enquanto o e-mail não é confirmado (nunca em texto, nunca em log). */
    public function up(): void
    {
        Schema::table('intelligence_trial_pendentes', function (Blueprint $table) {
            $table->string('senha_hash')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_trial_pendentes', function (Blueprint $table) {
            $table->dropColumn('senha_hash');
        });
    }
};

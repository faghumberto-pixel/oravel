<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App\Models\Branch já declarava 'city'/'state' no $fillable (comentário
 * "Adicionado" no próprio model), mas a migration original nunca criou
 * essas colunas -- qualquer mass-assignment com esses campos quebrava
 * (SQLSTATE 42703, coluna inexistente). Achado ao popular dados de demo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('city')->nullable()->after('description');
            $table->string('state', 2)->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['city', 'state']);
        });
    }
};

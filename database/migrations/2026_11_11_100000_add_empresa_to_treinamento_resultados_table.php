<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O teste público passa a perguntar a empresa, para acompanhar a
     * participação por cliente. tenant_id liga ao cliente cadastrado quando o
     * nome bate com um cliente (nulo se não houver correspondência única).
     */
    public function up(): void
    {
        Schema::table('treinamento_resultados', function (Blueprint $table) {
            $table->string('empresa', 120)->nullable()->after('funcao');
            $table->foreignUuid('tenant_id')->nullable()->after('empresa')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('treinamento_resultados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('empresa');
        });
    }
};

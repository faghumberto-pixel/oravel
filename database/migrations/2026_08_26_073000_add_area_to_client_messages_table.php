<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Área nullable de propósito -- mensagens enviadas antes desta feature
 * ficam sem área (visíveis a todos, fallback seguro), não vira campo
 * obrigatório no banco só no form do Portal do Cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_messages', function (Blueprint $table) {
            $table->string('area')->nullable()->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('client_messages', function (Blueprint $table) {
            $table->dropColumn('area');
        });
    }
};

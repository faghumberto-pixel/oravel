<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // Tabela vazia (0 linhas) -- dropar e recriar a coluna evita o erro de cast
            // automatico do Postgres (bigint -> uuid nao e permitido sem USING).
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn('notifiable_id');
            });
            Schema::table('notifications', function (Blueprint $table) {
                $table->uuid('notifiable_id')->after('notifiable_type');
                $table->index(['notifiable_type', 'notifiable_id']);
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn('notifiable_id');
            });
            Schema::table('notifications', function (Blueprint $table) {
                $table->unsignedBigInteger('notifiable_id')->after('notifiable_type');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Adiciona a coluna job_title como string, pode ser nullable ou ter um valor padrão
            $table->string('job_title')->nullable()->after('email'); // Ou após outro campo relevante
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('job_title');
        });
    }
};
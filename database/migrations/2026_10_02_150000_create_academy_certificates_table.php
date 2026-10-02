<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Certificado de conclusao de curso (do TENANT). Guarda uma copia dos nomes na emissao,
        // pra o certificado nao mudar se o usuario/curso forem renomeados depois.
        Schema::create('academy_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('user_id')->index();
            $table->uuid('course_id')->index();
            $table->string('code', 20)->unique();      // codigo publico de verificacao
            $table->string('user_name');
            $table->string('tenant_name');
            $table->string('course_title');
            $table->unsignedInteger('study_minutes')->default(0);
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_certificates');
    }
};

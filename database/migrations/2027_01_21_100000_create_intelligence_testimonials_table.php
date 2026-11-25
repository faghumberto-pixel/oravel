<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Depoimentos REAIS da página oravel.com.br/intelligence. Só aparece o que está aprovado E tem autorização de uso registrada. */
    public function up(): void
    {
        Schema::create('intelligence_testimonials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome', 120);
            $table->string('cargo', 120)->nullable();
            $table->string('empresa', 120)->nullable();           // opcional: o cliente pode preferir não ser identificado
            $table->text('texto');
            $table->boolean('autorizacao_uso')->default(false);   // o cliente autorizou publicar
            $table->text('autorizacao_registro')->nullable();     // como e quando autorizou (e-mail, WhatsApp, data)
            $table->boolean('aprovado')->default(false);          // revisado pela Oravel
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
            $table->index(['aprovado', 'autorizacao_uso', 'ordem']);
        });

        // Garantia no banco: nada fica aprovado sem autorização de uso.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE intelligence_testimonials ADD CONSTRAINT intelligence_testimonials_autorizacao_ck CHECK (NOT aprovado OR autorizacao_uso)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_testimonials');
    }
};

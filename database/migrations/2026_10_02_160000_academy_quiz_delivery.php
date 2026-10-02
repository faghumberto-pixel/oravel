<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prova entregue de uma vez: questao em branco tambem vira linha (sem alternativa, errada = zero).
        DB::statement('ALTER TABLE lesson_answers ALTER COLUMN selected_index DROP NOT NULL');

        // Uma entrega por usuario e aula (do TENANT). Depois de entregue nao refaz: o gabarito ja foi mostrado.
        Schema::create('lesson_quiz_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('user_id')->index();
            $table->foreignUuid('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->unsignedSmallInteger('total_questions');
            $table->unsignedSmallInteger('correct_answers');
            $table->timestamp('delivered_at');
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_quiz_submissions');
        DB::statement('DELETE FROM lesson_answers WHERE selected_index IS NULL');
        DB::statement('ALTER TABLE lesson_answers ALTER COLUMN selected_index SET NOT NULL');
    }
};

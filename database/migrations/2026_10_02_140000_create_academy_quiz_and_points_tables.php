<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Perguntas do quiz: conteudo GLOBAL, cadastrado na Central junto da aula.
        Schema::create('lesson_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->text('question');
            $table->json('options');                      // [{"text": "..."}, ...]
            $table->unsignedSmallInteger('correct_index'); // posicao (0 = primeira) da alternativa certa
            $table->text('explanation')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['lesson_id', 'position']);
        });

        // Resposta de cada usuario (do TENANT). Guarda so' a ultima tentativa + quantas foram.
        Schema::create('lesson_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('user_id')->index();
            $table->foreignUuid('question_id')->constrained('lesson_questions')->cascadeOnDelete();
            $table->unsignedSmallInteger('selected_index');
            $table->boolean('is_correct');
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'question_id']);
        });

        // Livro-razao de pontos (do TENANT). Uma linha por (usuario, origem, referencia): cada
        // acao pontua uma vez so'. Para 'time', a linha acumula os segundos ativos na aula.
        Schema::create('academy_points', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('user_id')->index();
            $table->string('source', 16);        // read | quiz | time | course
            $table->uuid('ref_id');              // aula, pergunta ou curso
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('seconds')->default(0);
            $table->timestamp('last_beat_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'source', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_points');
        Schema::dropIfExists('lesson_answers');
        Schema::dropIfExists('lesson_questions');
    }
};

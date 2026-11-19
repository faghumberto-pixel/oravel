<?php

use App\Relacionamento\Models\RelAcaoRegra;
use App\Relacionamento\Models\RelScoreRegra;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Relacionamento Oravel, Fase 2: pesos do score, regras de proxima acao e interesses por empresa. Global. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rel_score_regras', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('tipo', 30)->unique();
            $t->smallInteger('pontos')->default(0);
            $t->unsignedSmallInteger('limite')->default(3); // maximo de ocorrencias que contam (anti-inflar)
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });

        Schema::create('rel_acao_regras', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->unsignedTinyInteger('score_min');
            $t->unsignedTinyInteger('score_max');
            $t->string('titulo', 120);
            $t->text('descricao')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });

        Schema::create('rel_interesses', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('prospect_id')->nullable()->constrained('prospects')->cascadeOnDelete();
            $t->foreignUuid('sales_lead_id')->nullable()->constrained('sales_leads')->cascadeOnDelete();
            $t->string('interesse', 30);
            $t->string('origem', 10)->default('manual');
            $t->timestamps();
            $t->unique(['prospect_id', 'interesse']);
            $t->unique(['sales_lead_id', 'interesse']);
        });

        $agora = now();
        foreach (RelScoreRegra::PADRAO as $tipo => [$pontos, $limite]) {
            DB::table('rel_score_regras')->insert(['id' => (string) Str::uuid(), 'tipo' => $tipo, 'pontos' => $pontos, 'limite' => $limite, 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora]);
        }
        foreach (RelAcaoRegra::PADRAO as [$min, $max, $titulo, $descricao]) {
            DB::table('rel_acao_regras')->insert(['id' => (string) Str::uuid(), 'score_min' => $min, 'score_max' => $max, 'titulo' => $titulo, 'descricao' => $descricao, 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rel_interesses');
        Schema::dropIfExists('rel_acao_regras');
        Schema::dropIfExists('rel_score_regras');
    }
};

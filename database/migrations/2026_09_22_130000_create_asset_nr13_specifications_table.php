<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extensão 1:1 do Asset para equipamentos sujeitos à NR-13 (caldeiras, vasos de pressão,
 * tubulações e tanques) -- mesmo padrão de asset_forklift_specifications/
 * asset_platform_specifications/asset_generator_specifications (App\Domain\Fleet\Models):
 * não altera a tabela assets (45 colunas, 25 migrations de histórico), só estende via
 * relação hasOne quando o ativo é marcado como sujeito à norma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_nr13_specifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->boolean('subject_to_nr13')->default(true);
            $table->string('tipo_equipamento'); // caldeira | vaso_pressao | tubulacao | tanque
            $table->string('categoria_risco')->nullable(); // livre: A/B/C (caldeira), I..V (vaso) -- ver Nr13InspectionPeriodicity
            $table->string('tag_nr13')->nullable(); // TAG/identificação específica da norma, se diferente do tag do Asset
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->unique('asset_id'); // 1:1
            $table->index(['tenant_id', 'tipo_equipamento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_nr13_specifications');
    }
};

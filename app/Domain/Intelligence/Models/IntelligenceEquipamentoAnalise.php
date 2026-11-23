<?php

namespace App\Domain\Intelligence\Models;

use App\Domain\Intelligence\Concerns\Imutavel;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado calculado de um equipamento num snapshot (indicadores, classificação e o motivo escrito). Gravado, não recalculado na
 * leitura: o histórico não muda quando uma regra mudar (`versao_regras`). Sem metadados SaaS próprios: é acessado pelo módulo
 * "Intelligence - Equipamentos".
 */
class IntelligenceEquipamentoAnalise extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use Imutavel;

    protected $table = 'intelligence_equipamento_analises';

    protected $fillable = ['tenant_id', 'snapshot_id', 'equipamento_id', 'patrimonio', 'utilizacao_pct', 'disponibilidade_pct', 'retorno_anual_pct', 'razao_custo_valor',
        'custo_sobre_receita_pct', 'reincidencia_pct', 'idade_anos', 'classificacao', 'motivo', 'tendencia', 'versao_regras', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function equipamento(): BelongsTo
    {
        return $this->belongsTo(IntelligenceEquipamento::class, 'equipamento_id');
    }
}

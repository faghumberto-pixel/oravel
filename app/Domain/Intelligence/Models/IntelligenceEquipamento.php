<?php

namespace App\Domain\Intelligence\Models;

use App\Domain\Intelligence\Concerns\Imutavel;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Um equipamento na fotografia do mês: o que a empresa informou na aba "Equipamentos". Imutável. */
class IntelligenceEquipamento extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use Imutavel;

    protected $table = 'intelligence_equipamentos';

    protected static ?string $saasFeatureKey = 'tabela_intelligence_equipamentos';

    protected static ?string $saasPermissionSlug = 'intelligence_equipamento';

    protected static ?string $saasModuleLabel = 'Intelligence - Equipamentos';

    protected $fillable = ['tenant_id', 'snapshot_id', 'patrimonio', 'nome', 'categoria', 'ano_fabricacao', 'valor', 'receita', 'custo_mes', 'custo_acumulado',
        'os_corretivas', 'os_preventivas', 'reincidencias', 'dias_locado', 'dias_indisponivel', 'created_at'];

    protected $casts = ['created_at' => 'datetime', 'valor' => 'decimal:2', 'receita' => 'decimal:2', 'custo_mes' => 'decimal:2', 'custo_acumulado' => 'decimal:2'];

    public function analise(): HasOne
    {
        return $this->hasOne(IntelligenceEquipamentoAnalise::class, 'equipamento_id');
    }
}

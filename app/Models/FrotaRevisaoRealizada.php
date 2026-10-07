<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Revisão feita num veículo. Histórico: não se edita nem se apaga. */
class FrotaRevisaoRealizada extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'frota_revisoes_realizadas';

    protected $fillable = ['tenant_id', 'plano_id', 'ativo_id', 'realizada_em', 'odometro', 'custo', 'ordem_servico_id', 'observacoes', 'realizado_por'];

    protected $casts = ['realizada_em' => 'date', 'odometro' => 'integer', 'custo' => 'decimal:2'];

    public function plano(): BelongsTo
    {
        return $this->belongsTo(FrotaPlanoRevisao::class, 'plano_id');
    }
}

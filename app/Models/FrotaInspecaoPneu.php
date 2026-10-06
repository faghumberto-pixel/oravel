<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Medição de sulco e pressão. Sem tenant_id (isolamento pelo pneu/veículo). pneu_id nulo = menor sulco do veículo (checklist). */
class FrotaInspecaoPneu extends Model
{
    use HasUuids;

    protected $table = 'frota_inspecoes_pneu';

    protected $fillable = ['pneu_id', 'ativo_id', 'checklist_id', 'sulco_mm', 'pressao_psi', 'odometro', 'inspecionado_em', 'observacao'];

    protected $casts = ['sulco_mm' => 'decimal:1', 'pressao_psi' => 'decimal:1', 'inspecionado_em' => 'datetime', 'odometro' => 'integer'];

    public function pneu(): BelongsTo
    {
        return $this->belongsTo(FrotaPneu::class, 'pneu_id');
    }

    public function ativo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }
}

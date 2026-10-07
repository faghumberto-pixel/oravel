<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Retirada de uma chave: quem levou, quando, por quê e quando devolveu. Histórico: não se edita nem se apaga. */
class FrotaEntregaChave extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'frota_entregas_chave';

    protected $fillable = [
        'tenant_id', 'chave_id', 'motorista_id', 'responsavel_nome', 'entregue_em', 'motivo', 'observacoes_entrega',
        'devolvida_em', 'observacoes_devolucao', 'entregue_por', 'devolucao_registrada_por',
    ];

    protected $casts = ['entregue_em' => 'datetime', 'devolvida_em' => 'datetime'];

    public function responsavel(): string
    {
        return $this->motorista?->name ?? $this->responsavel_nome ?? '—';
    }

    public function chave(): BelongsTo
    {
        return $this->belongsTo(FrotaChave::class, 'chave_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(FleetDriver::class, 'motorista_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Instalação genérica de um componente (pneu ou bateria) em um veículo, com histórico de posição e odômetro. */
class FrotaInstalacaoComponente extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'frota_instalacoes_componente';

    protected $fillable = [
        'tenant_id', 'ativo_id', 'componente_type', 'componente_id', 'posicao', 'instalado_em', 'odometro_instalacao',
        'removido_em', 'odometro_remocao', 'motivo_remocao', 'criado_por',
    ];

    protected $casts = ['instalado_em' => 'datetime', 'removido_em' => 'datetime', 'odometro_instalacao' => 'integer', 'odometro_remocao' => 'integer'];

    public function scopeAbertas(Builder $query): Builder
    {
        return $query->whereNull('removido_em');
    }

    public function componente(): MorphTo
    {
        return $this->morphTo();
    }

    public function ativo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }

    public function estaAberta(): bool
    {
        return $this->removido_em === null;
    }
}

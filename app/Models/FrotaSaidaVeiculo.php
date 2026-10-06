<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Saída (e depois a entrada de volta) de um veículo da frota. Sem retorno = o veículo está fora. */
class FrotaSaidaVeiculo extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_saidas_veiculo';

    protected static ?string $saasPermissionSlug = 'saida_veiculo_frota';

    protected static ?string $saasModuleLabel = 'Entrada e Saída de Veículos';

    protected $table = 'frota_saidas_veiculo';

    /** Fora há mais de N horas vira pendência (atenção). */
    public const AVISO_FORA_HORAS = 24;

    protected $fillable = [
        'tenant_id', 'ativo_id', 'motorista_id', 'condutor_nome', 'finalidade', 'destino', 'motivo', 'cliente_id', 'ordem_servico_id',
        'saida_em', 'odometro_saida', 'combustivel_saida', 'checklist_saida_id', 'observacoes_saida', 'registrado_por',
        'retorno_em', 'odometro_retorno', 'combustivel_retorno', 'checklist_retorno_id', 'observacoes_retorno', 'retorno_registrado_por',
    ];

    protected $casts = ['saida_em' => 'datetime', 'retorno_em' => 'datetime', 'odometro_saida' => 'integer', 'odometro_retorno' => 'integer'];

    /** @return array<string, string> */
    public static function finalidadeLabels(): array
    {
        return [
            'visita_tecnica' => 'Visita técnica', 'administrativo' => 'Administrativo', 'diretoria' => 'Diretoria',
            'cliente' => 'Para cliente', 'locacao' => 'Locação', 'manutencao' => 'Manutenção', 'outro' => 'Outro',
        ];
    }

    public function scopeFora(Builder $query): Builder
    {
        return $query->whereNull('retorno_em');
    }

    public function estaFora(): bool
    {
        return $this->retorno_em === null;
    }

    public function kmRodado(): ?int
    {
        return $this->odometro_retorno !== null ? max(0, $this->odometro_retorno - $this->odometro_saida) : null;
    }

    public function condutor(): string
    {
        return $this->motorista?->name ?? $this->condutor_nome ?? '—';
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(FleetDriver::class, 'motorista_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'cliente_id');
    }

    public function checklistSaida(): BelongsTo
    {
        return $this->belongsTo(FrotaChecklist::class, 'checklist_saida_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Troca ou reposição de óleo de um veículo. Histórico: não se edita nem se apaga. */
class FrotaTrocaOleo extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_trocas_oleo';

    protected static ?string $saasPermissionSlug = 'troca_oleo_frota';

    protected static ?string $saasModuleLabel = 'Trocas de Óleo da Frota';

    protected $table = 'frota_trocas_oleo';

    public const TROCA = 'troca';

    public const REPOSICAO = 'reposicao';

    /** Consumo anormal: litros repostos por 1.000 km rodados desde a última troca (constante simples por enquanto). */
    public const LIMITE_REPOSICAO_LITROS_POR_1000_KM = 1.0;

    /** Aviso de troca próxima: até 500 km ou 15 dias do vencimento. */
    public const AVISO_KM = 500;

    public const AVISO_DIAS = 15;

    protected $fillable = [
        'peca_id', 'almoxarifado_id',
        'tenant_id', 'ativo_id', 'tipo', 'odometro', 'litros', 'produto', 'lote', 'custo', 'realizado_por', 'realizado_em',
        'proxima_troca_odometro', 'proxima_troca_data', 'observacoes', 'saida_item_agregado_id', 'coleta_oleo_usado_id',
    ];

    protected $casts = [
        'litros' => 'integer', 'custo' => 'decimal:2', 'realizado_em' => 'datetime', 'proxima_troca_data' => 'date',
        'odometro' => 'integer', 'proxima_troca_odometro' => 'integer',
    ];

    /** @return array<string, string> */
    public static function tipoLabels(): array
    {
        return [self::TROCA => 'Troca', self::REPOSICAO => 'Reposição'];
    }

    /** Trocas (não reposições) cujo óleo usado ainda não tem coleta registrada. */
    public function scopePendenteDeDestinacao(Builder $query): Builder
    {
        return $query->where('tipo', self::TROCA)->whereNull('coleta_oleo_usado_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }

    public function coleta(): BelongsTo
    {
        return $this->belongsTo(FrotaColetaOleoUsado::class, 'coleta_oleo_usado_id');
    }

    public function realizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'realizado_por');
    }
}

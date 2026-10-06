<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Multa de trânsito de um veículo da frota. */
class FrotaMulta extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_multas';

    protected static ?string $saasPermissionSlug = 'multa_frota';

    protected static ?string $saasModuleLabel = 'Multas da Frota';

    public const ABERTA = 'aberta';

    public const CONDUTOR_INDICADO = 'condutor_indicado';

    public const RECORRIDA = 'recorrida';

    public const PAGA = 'paga';

    public const CANCELADA = 'cancelada';

    /** Pontos por gravidade (tabela do CTB). */
    public const PONTOS = ['leve' => 3, 'media' => 4, 'grave' => 5, 'gravissima' => 7];

    /** Pontos do motorista em 12 meses: atenção a partir de 15, crítica a partir de 20 (limite de suspensão). */
    public const PONTOS_ATENCAO = 15;

    public const PONTOS_CRITICO = 20;

    /** Vencimento da multa em até N dias = atenção. */
    public const AVISO_VENCIMENTO_DIAS = 7;

    protected $attributes = ['situacao' => self::ABERTA, 'quem_paga' => 'empresa', 'pontos' => 0];

    protected $fillable = [
        'tenant_id', 'ativo_id', 'motorista_id', 'saida_veiculo_id', 'numero_auto', 'infracao_em', 'local', 'codigo_infracao', 'descricao',
        'gravidade', 'pontos', 'valor', 'vencimento', 'prazo_indicacao', 'situacao', 'condutor_indicado_em', 'pago_em', 'quem_paga',
        'observacoes', 'registrado_por',
    ];

    protected $casts = [
        'infracao_em' => 'datetime', 'vencimento' => 'date', 'prazo_indicacao' => 'date', 'condutor_indicado_em' => 'date', 'pago_em' => 'date',
        'valor' => 'decimal:2', 'pontos' => 'integer',
    ];

    /** @return array<string, string> */
    public static function gravidadeLabels(): array
    {
        return ['leve' => 'Leve', 'media' => 'Média', 'grave' => 'Grave', 'gravissima' => 'Gravíssima'];
    }

    /** @return array<string, string> */
    public static function situacaoLabels(): array
    {
        return [self::ABERTA => 'Aberta', self::CONDUTOR_INDICADO => 'Condutor indicado', self::RECORRIDA => 'Recorrida', self::PAGA => 'Paga', self::CANCELADA => 'Cancelada'];
    }

    /** Multas que ainda pedem ação (não pagas nem canceladas). */
    public function scopeEmAberto(Builder $query): Builder
    {
        return $query->whereIn('situacao', [self::ABERTA, self::CONDUTOR_INDICADO, self::RECORRIDA]);
    }

    public function encerrada(): bool
    {
        return in_array($this->situacao, [self::PAGA, self::CANCELADA], true);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(FleetDriver::class, 'motorista_id');
    }

    public function saida(): BelongsTo
    {
        return $this->belongsTo(FrotaSaidaVeiculo::class, 'saida_veiculo_id');
    }
}

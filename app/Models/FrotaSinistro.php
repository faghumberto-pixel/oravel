<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** Sinistro ou ocorrência de um veículo da frota, com fotos e documentos (B.O., laudos). */
class FrotaSinistro extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use InteractsWithMedia;

    protected static ?string $saasFeatureKey = 'tabela_frota_sinistros';

    protected static ?string $saasPermissionSlug = 'sinistro_frota';

    protected static ?string $saasModuleLabel = 'Sinistros da Frota';

    public const ABERTO = 'aberto';

    public const EM_ORCAMENTO = 'em_orcamento';

    public const EM_REPARO = 'em_reparo';

    public const ENCERRADO = 'encerrado';

    public const CANCELADO = 'cancelado';

    /** Veículo parado há N dias ou mais = crítica; sem orçamento após N dias = atenção. */
    public const PARADO_CRITICO_DIAS = 15;

    public const SEM_ORCAMENTO_DIAS = 7;

    protected $attributes = ['situacao' => self::ABERTO, 'culpa' => 'indefinida', 'houve_vitima' => false, 'veiculo_parado' => false];

    protected $fillable = [
        'tenant_id', 'ativo_id', 'motorista_id', 'saida_veiculo_id', 'ordem_servico_id', 'tipo', 'ocorrido_em', 'local', 'descricao', 'culpa',
        'houve_vitima', 'bo_numero', 'seguradora', 'apolice', 'numero_sinistro_seguradora', 'valor_orcamento', 'valor_franquia',
        'veiculo_parado', 'parado_desde', 'voltou_a_rodar_em', 'situacao', 'encerrado_em', 'observacoes', 'registrado_por',
    ];

    protected $casts = [
        'ocorrido_em' => 'datetime', 'parado_desde' => 'datetime', 'voltou_a_rodar_em' => 'datetime', 'encerrado_em' => 'datetime',
        'houve_vitima' => 'boolean', 'veiculo_parado' => 'boolean', 'valor_orcamento' => 'decimal:2', 'valor_franquia' => 'decimal:2',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('fotos')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
        $this->addMediaCollection('documentos')->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
    }

    /** @return array<string, string> */
    public static function tipoLabels(): array
    {
        return ['colisao' => 'Colisão', 'avaria' => 'Avaria', 'furto_roubo' => 'Furto/roubo', 'incendio' => 'Incêndio', 'alagamento' => 'Alagamento', 'outro' => 'Outro'];
    }

    /** @return array<string, string> */
    public static function culpaLabels(): array
    {
        return ['propria' => 'Própria', 'terceiro' => 'Do terceiro', 'indefinida' => 'Indefinida'];
    }

    /** @return array<string, string> */
    public static function situacaoLabels(): array
    {
        return [self::ABERTO => 'Aberto', self::EM_ORCAMENTO => 'Em orçamento', self::EM_REPARO => 'Em reparo', self::ENCERRADO => 'Encerrado', self::CANCELADO => 'Cancelado'];
    }

    public function encerrado(): bool
    {
        return in_array($this->situacao, [self::ENCERRADO, self::CANCELADO], true);
    }

    /** Veículo ainda parado por este sinistro. */
    public function parado(): bool
    {
        return $this->veiculo_parado && $this->voltou_a_rodar_em === null && $this->situacao !== self::CANCELADO;
    }

    /** Dias parado (até voltar a rodar, ou até hoje). */
    public function diasParado(): ?int
    {
        if (! $this->veiculo_parado || ! $this->parado_desde) {
            return null;
        }
        $fim = $this->voltou_a_rodar_em ?? now();

        return intdiv(max(0, $fim->timestamp - $this->parado_desde->timestamp), 86400);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(FleetDriver::class, 'motorista_id');
    }

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'ordem_servico_id');
    }
}

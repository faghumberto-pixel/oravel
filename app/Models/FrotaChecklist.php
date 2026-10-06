<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Checklist de SAÍDA ou RETORNO de um veículo (ativo do tipo veículo). Situação: ok, atencao, bloqueado
 * (item crítico com problema: o veículo não sai) ou liberado (bloqueio liberado com motivo).
 */
class FrotaChecklist extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use InteractsWithMedia;

    protected static ?string $saasFeatureKey = 'tabela_frota_checklists';

    protected static ?string $saasPermissionSlug = 'checklist_frota';

    protected static ?string $saasModuleLabel = 'Checklists da Frota';

    protected $table = 'frota_checklists';

    public const TIPO_SAIDA = 'saida';

    public const TIPO_RETORNO = 'retorno';

    public const OK = 'ok';

    public const ATENCAO = 'atencao';

    public const BLOQUEADO = 'bloqueado';

    public const LIBERADO = 'liberado';

    /** Dias sem checklist COMPLETO a partir dos quais o veículo fica pendente (constante simples por enquanto). */
    public const DIAS_CHECKLIST_COMPLETO = 7;

    protected $fillable = [
        'tenant_id', 'ativo_id', 'motorista_id', 'modelo_id', 'preenchido_por', 'tipo', 'odometro', 'nivel_combustivel', 'situacao',
        'liberado_por', 'liberado_em', 'motivo_liberacao', 'assinatura', 'observacoes', 'concluido_em', 'checklist_par_id',
        'movimentacao_equipamento_id', 'ordem_servico_id',
    ];

    protected $casts = ['liberado_em' => 'datetime', 'concluido_em' => 'datetime', 'odometro' => 'integer'];

    /** @return array<string, string> */
    public static function tipoLabels(): array
    {
        return [self::TIPO_SAIDA => 'Saída', self::TIPO_RETORNO => 'Retorno'];
    }

    /** @return array<string, string> */
    public static function situacaoLabels(): array
    {
        return [self::OK => 'OK', self::ATENCAO => 'Atenção', self::BLOQUEADO => 'Bloqueado', self::LIBERADO => 'Liberado'];
    }

    /** @return array<string, string> */
    public static function combustivelLabels(): array
    {
        return ['reserva' => 'Reserva', '1/4' => '1/4', '1/2' => '1/2', '3/4' => '3/4', 'cheio' => 'Cheio'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('laterais')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
        $this->addMediaCollection('problemas')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('miniatura')->fit(Fit::Crop, 160, 160)->nonQueued();
    }

    public function ativo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(FleetDriver::class, 'motorista_id');
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(FrotaModeloChecklist::class, 'modelo_id');
    }

    public function preenchidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preenchido_por');
    }

    public function liberadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liberado_por');
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(FrotaRespostaChecklist::class, 'checklist_id');
    }

    /** Checklist de saída que este retorno fecha (null se não houver). */
    public function checklistPar(): BelongsTo
    {
        return $this->belongsTo(self::class, 'checklist_par_id');
    }

    public function ehCompleto(): bool
    {
        return $this->modelo?->tipo === FrotaModeloChecklist::TIPO_COMPLETO;
    }

    public function estaBloqueado(): bool
    {
        return $this->situacao === self::BLOQUEADO;
    }

    /**
     * No RETORNO: itens com problema que na saída pareada estavam OK (dano novo, ocorrido com este motorista).
     *
     * @return Collection<int, FrotaRespostaChecklist>
     */
    public function novosProblemasNoRetorno(): Collection
    {
        if ($this->tipo !== self::TIPO_RETORNO || ! $this->checklistPar) {
            return collect();
        }

        $okNaSaida = $this->checklistPar->respostas()->where('resultado', FrotaRespostaChecklist::OK)->pluck('descricao_registrada')->all();

        return $this->respostas()
            ->where('resultado', FrotaRespostaChecklist::PROBLEMA)
            ->get()
            ->filter(fn (FrotaRespostaChecklist $r) => in_array($r->descricao_registrada, $okNaSaida, true))
            ->values();
    }
}

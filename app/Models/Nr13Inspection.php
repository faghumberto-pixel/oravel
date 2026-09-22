<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Histórico de inspeções/ensaios de um equipamento NR-13 (interna, externa, de segurança,
 * hidrostática). data_proxima_inspecao é gravada no momento do registro (não recalculada
 * dinamicamente): trocar a periodicidade configurada depois não deve reescrever inspeções
 * já feitas -- mesmo raciocínio de EmployeeCertification.data_validade.
 */
class Nr13Inspection extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;
    use InteractsWithMedia;

    protected $table = 'nr13_inspections';

    protected static ?string $saasFeatureKey = 'tabela_nr13_inspections';

    protected static ?string $saasPermissionSlug = 'inspecao_nr13';

    protected static ?string $saasModuleLabel = 'Inspeções NR-13';

    public const TIPO_INTERNA = 'interna';

    public const TIPO_EXTERNA = 'externa';

    public const TIPO_SEGURANCA = 'seguranca';

    public const TIPO_HIDROSTATICA = 'hidrostatica';

    public const RESULTADO_APROVADO = 'aprovado';

    public const RESULTADO_REPROVADO = 'reprovado';

    public const RESULTADO_COM_RESSALVA = 'com_ressalva';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'tipo',
        'data_inspecao',
        'data_proxima_inspecao',
        'resultado',
        'responsavel_tecnico',
        'numero_art',
        'observacoes',
    ];

    protected $casts = [
        'data_inspecao' => 'date',
        'data_proxima_inspecao' => 'date',
    ];

    public static function tipoLabels(): array
    {
        return [
            self::TIPO_INTERNA => 'Interna',
            self::TIPO_EXTERNA => 'Externa',
            self::TIPO_SEGURANCA => 'De Segurança',
            self::TIPO_HIDROSTATICA => 'Hidrostática',
        ];
    }

    public static function resultadoLabels(): array
    {
        return [
            self::RESULTADO_APROVADO => 'Aprovado',
            self::RESULTADO_REPROVADO => 'Reprovado',
            self::RESULTADO_COM_RESSALVA => 'Aprovado com ressalva',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('laudo')->singleFile();
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function isVencida(): bool
    {
        return $this->data_proxima_inspecao && $this->data_proxima_inspecao->isPast();
    }

    public function isProximaDoVencimento(int $dias = 30): bool
    {
        return $this->data_proxima_inspecao
            && ! $this->isVencida()
            && $this->data_proxima_inspecao->lessThanOrEqualTo(now()->addDays($dias));
    }
}

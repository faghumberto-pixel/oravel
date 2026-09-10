<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Metadados de EPI sobre um Material (CA, fabricante, vida util, tipo) --
 * 1:1, mesmo padrao de AssetForkliftSpecification/AssetPlatformSpecification.
 * Cada tamanho/variante de EPI (luva P/M/G, bota por numeracao) e' seu
 * proprio Material com sua propria linha aqui; nao existe model de
 * variante, o CA costuma se repetir entre irmaos de tamanho (reflete a
 * realidade: o numero do CA e' o mesmo independente do tamanho).
 */
class EpiSpecification extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use SoftDeletes;

    protected static ?string $saasFeatureKey = 'tabela_epi_specifications';

    protected static ?string $saasPermissionSlug = 'especificacao_epi';

    protected static ?string $saasModuleLabel = 'Especificações de EPI';

    public const TYPE_LUVA = 'luva';

    public const TYPE_OCULOS = 'oculos';

    public const TYPE_CAPACETE = 'capacete';

    public const TYPE_PROTETOR_AURICULAR = 'protetor_auricular';

    public const TYPE_BOTA = 'bota';

    public const TYPE_CINTO_SEGURANCA = 'cinto_seguranca';

    public const TYPE_MASCARA = 'mascara';

    public const TYPE_AVENTAL = 'avental';

    public const TYPE_PROTETOR_FACIAL = 'protetor_facial';

    public const TYPE_UNIFORME = 'uniforme';

    public const TYPE_OUTRO = 'outro';

    public const OWNERSHIP_DEFINITIVA = 'definitiva';

    public const OWNERSHIP_EMPRESTIMO_TEMPORARIO = 'emprestimo_temporario';

    protected $fillable = [
        'tenant_id',
        'material_id',
        'epi_type',
        'size_label',
        'ca_number',
        'ca_manufacturer',
        'ca_validade',
        'estimated_lifespan_days',
        'default_ownership_mode',
        'notes',
    ];

    protected $casts = [
        'ca_validade' => 'date',
        'estimated_lifespan_days' => 'integer',
    ];

    protected $attributes = [
        'default_ownership_mode' => self::OWNERSHIP_DEFINITIVA,
    ];

    public static function typeLabels(): array
    {
        return [
            self::TYPE_LUVA => 'Luva',
            self::TYPE_OCULOS => 'Óculos de Proteção',
            self::TYPE_CAPACETE => 'Capacete',
            self::TYPE_PROTETOR_AURICULAR => 'Protetor Auricular',
            self::TYPE_BOTA => 'Bota/Calçado de Segurança',
            self::TYPE_CINTO_SEGURANCA => 'Cinto de Segurança',
            self::TYPE_MASCARA => 'Máscara/Respirador',
            self::TYPE_AVENTAL => 'Avental',
            self::TYPE_PROTETOR_FACIAL => 'Protetor Facial',
            self::TYPE_UNIFORME => 'Uniforme',
            self::TYPE_OUTRO => 'Outro',
        ];
    }

    public static function ownershipModeLabels(): array
    {
        return [
            self::OWNERSHIP_DEFINITIVA => 'Entrega Definitiva (uso pessoal)',
            self::OWNERSHIP_EMPRESTIMO_TEMPORARIO => 'Empréstimo Temporário (compartilhado)',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function isCaVencido(): bool
    {
        return $this->ca_validade->isPast();
    }

    public function isCaProximoVencimento(int $dias = 30): bool
    {
        return ! $this->isCaVencido()
            && $this->ca_validade->lessThanOrEqualTo(now()->addDays($dias));
    }
}

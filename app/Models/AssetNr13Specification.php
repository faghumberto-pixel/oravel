<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Extensão 1:1 do Asset pra equipamentos sujeitos à NR-13 (caldeiras, vasos de pressão,
 * tubulações e tanques) -- mesmo padrão de App\Domain\Fleet\Models\ForkliftSpecification:
 * hasOne a partir do Asset, editada inline no form do AssetResource (->relationship()),
 * nunca listada/autorizada de forma independente. Por isso, igual ao Forklift/Platform/
 * GeneratorSpecification, este model NÃO tem Policy própria (cai sob a autorização do
 * Asset pai quando salva via nested relationship; ver app/Policies -- nenhuma das
 * *Specification existentes tem uma).
 */
class AssetNr13Specification extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    protected $table = 'asset_nr13_specifications';

    protected static ?string $saasFeatureKey = 'tabela_asset_nr13_specifications';

    protected static ?string $saasPermissionSlug = 'conformidade_nr13';

    protected static ?string $saasModuleLabel = 'Conformidade NR-13';

    public const TIPO_CALDEIRA = 'caldeira';

    public const TIPO_VASO_PRESSAO = 'vaso_pressao';

    public const TIPO_TUBULACAO = 'tubulacao';

    public const TIPO_TANQUE = 'tanque';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'subject_to_nr13',
        'tipo_equipamento',
        'categoria_risco',
        'tag_nr13',
        'observacoes',
    ];

    protected $casts = [
        'subject_to_nr13' => 'boolean',
    ];

    public static function tipoEquipamentoLabels(): array
    {
        return [
            self::TIPO_CALDEIRA => 'Caldeira',
            self::TIPO_VASO_PRESSAO => 'Vaso de Pressão',
            self::TIPO_TUBULACAO => 'Tubulação',
            self::TIPO_TANQUE => 'Tanque',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item do kit de segurança de um veículo (extintor, triângulo, macaco, chave de roda...). */
class FrotaItemSeguranca extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_itens_seguranca';

    protected static ?string $saasPermissionSlug = 'item_seguranca_frota';

    protected static ?string $saasModuleLabel = 'Kit de Segurança da Frota';

    /** Validade vencendo em até N dias = atenção. */
    public const AVISO_VALIDADE_DIAS = 30;

    /** Sem conferência há mais de N dias = atenção. */
    public const CONFERENCIA_DIAS = 180;

    protected $table = 'frota_itens_seguranca';

    protected $attributes = ['obrigatorio' => true, 'tem_validade' => false, 'presente' => true, 'ativo' => true];

    protected $fillable = ['tenant_id', 'ativo_id', 'nome', 'identificacao', 'obrigatorio', 'tem_validade', 'validade', 'presente', 'conferido_em', 'observacoes', 'ativo'];

    protected $casts = [
        'obrigatorio' => 'boolean', 'tem_validade' => 'boolean', 'presente' => 'boolean', 'ativo' => 'boolean',
        'validade' => 'date', 'conferido_em' => 'date',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Funil de vendas do tenant (tabela crm_funis). O nome da classe evita
 * confundir com a página App\Filament\Pages\CrmFunil.
 */
class CrmPipeline extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected $table = 'crm_funis';

    protected static ?string $saasFeatureKey = 'tabela_crm_funis';

    protected static ?string $saasPermissionSlug = 'crm_funil';

    protected static ?string $saasModuleLabel = 'CRM - Funis';

    protected $fillable = ['tenant_id', 'nome', 'padrao', 'ativo'];

    protected $casts = ['padrao' => 'boolean', 'ativo' => 'boolean'];

    public function etapas(): HasMany
    {
        return $this->hasMany(CrmEtapa::class, 'crm_funil_id')->orderBy('ordem');
    }
}

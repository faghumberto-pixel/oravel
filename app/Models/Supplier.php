<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use BelongsToTenant, HasFactory, HasSaaSMetadata, HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_suppliers';

    protected static ?string $saasPermissionSlug = 'fornecedor';

    protected static ?string $saasModuleLabel = 'Fornecedores';

    protected $fillable = [
        'tenant_id',
        'name',
        'document',
        'email',
        'phone',
        'bank_account_pix',
        'compliance_ceis_cnep',
        'lista_trabalho_escravo',
        'termo_lgpd',
        'cnpj_card',
        'inscricao_estadual',
        'contrato_social',
        'cnd_federal',
        'crf_fgts',
        'cndt',
    ];

    protected $casts = [
        'compliance_ceis_cnep' => 'boolean',
        'lista_trabalho_escravo' => 'boolean',
        'termo_lgpd' => 'boolean',
    ];

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }
}

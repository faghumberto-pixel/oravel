<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'rating_avg',
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
        'rating_avg' => 'decimal:2',
    ];

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(SupplierCategory::class, 'supplier_category_supplier');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(SupplierEvaluation::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(SupplierContract::class);
    }

    /**
     * Historico de compras -- ver App\Models\PurchaseOrder::supplier() e
     * MaterialRequestQuotation::supplier() (inverso).
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(MaterialRequestQuotation::class);
    }

    public function accountPayables(): HasMany
    {
        return $this->hasMany(AccountPayable::class);
    }

    /**
     * Recalcula o cache rating_avg como media de todas as avaliacoes --
     * chamado por App\Observers\SupplierEvaluationObserver, mesmo padrao
     * de Material::recalculateCurrentStock().
     */
    public function recalculateRatingAvg(): void
    {
        $this->updateQuietly([
            'rating_avg' => round((float) $this->evaluations()->avg('score_medio'), 2),
        ]);
    }
}

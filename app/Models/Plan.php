<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    // Adicionado os novos campos comerciais no fillable
    protected $fillable = [
        'name', 
        'base_price',      // Preço cheio de tabela (ex: 2800.00)
        'discount_value',  // Valor do desconto aplicado (ex: 500.00 ou 20.00)
        'discount_type',   // 'fixed' (R$) ou 'percentage' (%)
        'final_price',     // O valor líquido real calculado (MRR)
        'billing_cycle',   // monthly, quarterly, semiannual, annual
        'campaign_tag',    // Identificador (ex: 'socio_fundador', 'promocao_anual')
        'features', 
        'is_active'
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'final_price' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Booted do Model: intercepta o evento 'saving' para calcular 
     * o preço final automaticamente antes de gravar no banco de dados.
     */
    protected static function booted()
    {
        static::saving(function (Plan $plan) {
            $basePrice = (float) ($plan->base_price ?? 0);
            $discountValue = (float) ($plan->discount_value ?? 0);

            // Se o desconto for em porcentagem (ex: 20% para plano Anual)
            if ($plan->discount_type === 'percentage') {
                $discountAmount = ($basePrice * $discountValue) / 100;
                $plan->final_price = max(0, $basePrice - $discountAmount);
            } else {
                // Se o desconto for valor fixo em Reais (ex: R$ 500 de desconto para Sócio Fundador)
                $plan->final_price = max(0, $basePrice - $discountValue);
            }
        });
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Método para checar se o plano possui o recurso (feature) comercial.
     */
    public function hasFeature(string $featureSlug): bool
    {
        return in_array($featureSlug, $this->features ?? []);
    }
}
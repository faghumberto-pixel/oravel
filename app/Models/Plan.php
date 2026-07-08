<?php

namespace App\Models;

use App\Support\SaaSRegistry;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasUuids;

    protected $table = 'plans';

    protected $fillable = [
        'name', 'price', 'level', 'base_price', 'discount_value', 'discount_type', 'final_price', 'billing_cycle', 'campaign_tag', 'features', 'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'base_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'final_price' => 'decimal:2',
        'is_active' => 'boolean',
        'level' => 'integer',
    ];

    /**
     * Validador estruturado para checagem de dicionário booleano obtido do pgsql
     * Resolve a exceção do arquivo app/Filament/Pages/Chat.php
     */
    public function hasFeature(string $featureSlug): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $features = $this->features;

        if (is_string($features)) {
            $features = json_decode($features, true) ?? [];
        }

        if (! is_array($features)) {
            return false;
        }

        // Se a chave existe no dicionário JSON gerado pela Central, valida o estado lógico
        if (array_key_exists($featureSlug, $features)) {
            return $features[$featureSlug] === true || $features[$featureSlug] === 1 || $features[$featureSlug] === 'true' || $features[$featureSlug] === '1';
        }

        // Fallback linear de contingência
        return in_array($featureSlug, $features, true);
    }

    /**
     * Lista de chaves de recursos/tabelas disponíveis para liberar num plano ou
     * como override de tenant, exibida nos CheckboxList de PlanResource e
     * TenantResource (painel Central).
     *
     * Gerada dinamicamente a partir de \App\Support\SaaSRegistry::modules() --
     * a mesma fonte única de verdade usada por RoleResource e AbstractPolicy --
     * então toda tabela/Model novo com a trait HasSaaSMetadata aparece aqui
     * sozinho, sem precisar editar este método. ChatRoom (feature 'modulo_chat')
     * também usa HasSaaSMetadata, então entra no loop abaixo como qualquer
     * outro módulo -- não precisa mais de entrada manual.
     */
    public static function getAvailableFeaturesOptions(): array
    {
        $options = [];

        foreach (SaaSRegistry::modules() as $module) {
            if (! $module['feature']) {
                continue;
            }
            $options[$module['feature']] = 'Tabela: '.($module['label'] ?? $module['slug']);
        }

        // Entrada manual: 'modulo_dashboard' nao tem Model/tabela por tras (e'
        // so a pagina inicial App\Filament\Pages\PainelGestao), entao nao
        // aparece no loop acima via HasSaaSMetadata. Migration
        // 2026_07_09_000000_backfill_modulo_dashboard_feature_on_plans deixou
        // todo plano existente com essa chave = true, pra ninguem perder o
        // Dashboard sem querer; desmarcar aqui esconde so o Dashboard.
        $options['modulo_dashboard'] = 'Painel: Dashboard (Painel de Controle)';

        return $options;
    }

    /**
     * MUTATOR LARAVEL 12: Gerencia o armazenamento JSON limpo no Postgres
     */
    protected function features(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value) {
                if (empty($value)) {
                    return [];
                }
                if (is_string($value)) {
                    $decoded = json_decode($value, true);

                    return is_array($decoded) ? $decoded : [];
                }

                return is_array($value) ? $value : [];
            },
            set: function (mixed $value) {
                return json_encode(is_array($value) ? $value : [], JSON_UNESCAPED_UNICODE);
            }
        );
    }
}

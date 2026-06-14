<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Plan extends Model
{
    use HasUuids;

    protected $table = 'plans';

    protected $fillable = [
        'name', 'price', 'level', 'base_price', 'discount_value', 'discount_type', 'final_price', 'billing_cycle', 'campaign_tag', 'features', 'is_active'
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'base_price'     => 'decimal:2',
        'discount_value' => 'decimal:2',
        'final_price'    => 'decimal:2',
        'is_active'      => 'boolean',
        'level'          => 'integer',
    ];

    /**
     * Validador estruturado para checagem de dicionário booleano obtido do pgsql
     * Resolve a exceção do arquivo app/Filament/Pages/Chat.php
     */
    public function hasFeature(string $featureSlug): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $features = $this->features;

        if (is_string($features)) {
            $features = json_decode($features, true) ?? [];
        }

        if (!is_array($features)) {
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
     * Lista amigável estática das 21 chaves de recursos e tabelas mapeadas no banco
     */
    public static function getAvailableFeaturesOptions(): array
    {
        return [
            'modulo_painel_gestao'       => 'Módulo: Painel de Gestão',
            'modulo_kanban_oficina'      => 'Módulo: Kanban da Oficina',
            'tabela_chat_rooms'          => 'Tabela: Salas de Chat',
            'tabela_roles'               => 'Tabela: Perfis e Funções',
            'tabela_users'               => 'Tabela: Usuários',
            'tabela_departments'         => 'Tabela: Departamentos',
            'tabela_clients'             => 'Tabela: Clientes',
            'tabela_suppliers'           => 'Tabela: Fornecedores',
            'tabela_assets'              => 'Tabela: Ativos / Frota',
            'tabela_materials'           => 'Tabela: Materiais',
            'tabela_material_categories' => 'Tabela: Categorias de Materiais',
            'tabela_maintenance_orders'  => 'Tabela: Ordens de Manutenção',
            'tabela_checklist_templates' => 'Tabela: Modelos de Checklist',
            'tabela_contracts'           => 'Tabela: Contratos',
            'tabela_purchases'           => 'Tabela: Compras',
            'tabela_purchase_orders'     => 'Tabela: Pedidos de Compra',
            'kanban_oficina'             => 'Painel Kanban Oficina',
            'chat_os'                    => 'Chat de Ordens de Serviço',
            'modulo_chat'                => 'Módulo: Chat Geral',
            'tabela_fleet_statuses'      => 'Tabela: Status da Frota',
            'painel_gestao'              => 'Painel de Gestão Direta',
        ];
    }

    /**
     * MUTATOR LARAVEL 12: Gerencia o armazenamento JSON limpo no Postgres
     */
    protected function features(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value) {
                if (empty($value)) return [];
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

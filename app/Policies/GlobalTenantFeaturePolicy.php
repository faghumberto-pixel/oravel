<?php

namespace App\Policies;

use Filament\Facades\Filament;
use Illuminate\Support\Str;

class GlobalTenantFeaturePolicy
{
    /**
     * Intercepta magicamente qualquer validação do Filament (viewAny, create, edit, etc.)
     */
    public function __call(string $method, array $arguments): bool
    {
        // 1. Regra de Ouro: Se for Administrador Master do sistema, libera tudo
        $user = auth()->user();
        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        // 2. Recupera o Tenant Ativo
        $tenant = Filament::getTenant();
        if (! $tenant) {
            return true;
        }

        // 3. Descobre qual Model/Resource está invocando a verificação
        // Captura o model a partir dos argumentos enviados pelo Gate do Laravel
        $modelClass = isset($arguments[1]) ? (is_string($arguments[1]) ? $arguments[1] : get_class($arguments[1])) : null;
        
        if (! $modelClass) {
            return true;
        }

        // Converte o nome do Model para a chave do plano (Ex: "App\Models\Supplier" -> "suppliers")
        $modelClassName = class_basename($modelClass);
        
        $mapeamentoFeatures = [
            'Asset'             => 'assets',
            'Client'            => 'clients',
            'Material'          => 'materials',
            'Supplier'          => 'suppliers',
            'MaintenanceOrder'  => 'maintenance_orders',
            'Category'          => 'categories',
            'ChecklistTemplate' => 'checklists',
        ];

        $featureKey = $mapeamentoFeatures[$modelClassName] ?? null;

        // Se o modelo não estiver na matriz de controle SaaS, permite o acesso padrão
        if (! $featureKey) {
            return true;
        }

        // 4. Valida se a feature está inclusa no array do plano do Tenant no PostgreSQL
        $featuresPermitidas = $tenant->plan->features ?? [];

        return in_array($featureKey, $featuresPermitidas);
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SaaSResourcePolicy
{
    private function getFeatureKeyForModel(string $modelClass): ?string
    {
        return match ($modelClass) {
            \App\Models\Client::class             => 'tabela_clients',
            \App\Models\Supplier::class           => 'tabela_suppliers',
            \App\Models\Asset::class              => 'tabela_assets',
            \App\Models\Material::class           => 'tabela_materials',
            \App\Models\MaterialCategory::class   => 'tabela_material_categories',
            \App\Models\MaintenanceOrder::class   => 'tabela_maintenance_orders',
            \App\Models\ChecklistTemplate::class  => 'tabela_checklist_templates',
            \App\Models\Contract::class           => 'tabela_contracts',
            \App\Models\Purchase::class           => 'tabela_purchases',
            \App\Models\FleetStatus::class        => 'tabela_fleet_statuses',
            \App\Models\User::class               => 'tabela_users',
            \App\Models\Department::class         => 'tabela_departments',
            \App\Models\ChatRoom::class           => 'tabela_chat_rooms',
            default => null
        };
    }

    private function getPermissionSlugForModel(string $modelClass): ?string
    {
        return match ($modelClass) {
            \App\Models\MaintenanceOrder::class   => 'ordem_servico',
            \App\Models\ChecklistTemplate::class  => 'checklist',
            \App\Models\User::class               => 'funcionario',
            \App\Models\Department::class         => 'departamento',
            \App\Models\Client::class             => 'cliente',
            \App\Models\Material::class           => 'material',
            \App\Models\MaterialCategory::class   => 'categoria_material',
            \App\Models\FleetStatus::class        => 'fila_logistica',
            \App\Models\ChatRoom::class           => 'chat',
            \App\Models\Supplier::class           => 'suprimentos',
            \App\Models\Purchase::class           => 'suprimentos',
            \App\Models\Asset::class              => 'ativo',
            default => null
        };
    }

    private function validateAccess(
        User $user,
        string $action = 'ler',
        ?string $modelClass = null,
        ?Model $record = null
    ): bool {
        $tenant = Filament::getTenant();

        if (!$tenant) {
            $tenantSlug = request()->segment(2) === 'admin' ? request()->segment(3) : request()->segment(2);
            if ($tenantSlug && $tenantSlug !== 'admin' && !str_contains($tenantSlug, '.')) {
                $tenant = Tenant::where('slug', $tenantSlug)->first();
            }
        }

        $targetModel = $modelClass ?? ($record ? get_class($record) : null);

        // 🛑 TRAVA COMERCIAL: feature não contratada bloqueia todo o tenant.
        if ($tenant && $targetModel) {
            $featureKey = $this->getFeatureKeyForModel($targetModel);
            if ($featureKey && !$tenant->hasFeature($featureKey)) {
                return false;
            }
        }

        // 1. SuperAdmin / Admin do tenant: acesso total ao que passou no plano.
        if ($user->isAdmin()) {
            return true;
        }

        if (!$tenant) {
            return false;
        }

        // 2. SEGREGAÇÃO DE DADOS: registro de outro tenant é negado.
        if ($record && isset($record->tenant_id) && $record->tenant_id !== $tenant->id) {
            Log::warning("Tentativa não autorizada: Usuário {$user->id} tentou acessar registro do tenant {$record->tenant_id}");
            return false;
        }

        // 3. PERMISSÃO INDIVIDUAL (role): o usuário precisa ter "{acao}_{slug}".
        if ($targetModel) {
            $slug = $this->getPermissionSlugForModel($targetModel);
            if ($slug) {
                $permission = "{$action}_{$slug}";
                if (!$user->hasPermissionTo($permission)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function viewAny(User $user, ?string $modelClass = null): bool
    {
        return $this->validateAccess($user, 'ler', $modelClass);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->validateAccess($user, 'ler', null, $record);
    }

    public function create(User $user): bool
    {
        return $this->validateAccess($user, 'criar');
    }

    public function update(User $user, Model $record): bool
    {
        return $this->validateAccess($user, 'editar', null, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->validateAccess($user, 'excluir', null, $record);
    }
}

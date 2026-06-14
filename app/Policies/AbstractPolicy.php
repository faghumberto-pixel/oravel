<?php

namespace App\Policies;

use App\Models\User;
use App\Support\SaaSRegistry;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Str;

abstract class AbstractPolicy
{
    use HandlesAuthorization;

    protected function isSameTenant(User $user, $model): bool
    {
        if (!isset($model->tenant_id)) {
            return true;
        }
        return (string) $user->tenant_id === (string) $model->tenant_id;
    }

    protected function check(User $user, string $action, $model = null): bool
    {
        // 1. Super admin da plataforma: acima de tudo.
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        // 2. TRAVA COMERCIAL SOBERANA: feature nao contratada (ou tenant sem plano)
        //    nega para todos, inclusive admin do tenant.
        $tenant = Filament::getTenant();
        if ($tenant) {
            if (!$tenant->relationLoaded('plan')) {
                $tenant->load(['plan' => fn($q) => $q->withoutGlobalScopes()]);
            }
            $plan = $tenant->plan;
            $featureKey = $this->getFeatureKeyFromModel($model);
            if ($featureKey) {
                if (!$plan || !$plan->hasFeature($featureKey)) {
                    return false;
                }
            }
        }

        // 3. Admin do tenant: passou na trava comercial, ve o modulo.
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        // 4. Permissao individual.
        $permission = $this->getPermissionName($action, $model);
        if (!$permission) {
            return false;
        }
        $hasPermission = $user->can($permission);
        if ($model && is_object($model) && $action !== 'create') {
            return $hasPermission && $this->isSameTenant($user, $model);
        }
        return $hasPermission;
    }

    /**
     * Resolve a classe do Model a partir do parâmetro recebido
     * (objeto, string de classe, ou null = deduz pelo nome da Policy).
     */
    protected function resolveModelClass($model): ?string
    {
        if (is_object($model)) {
            return get_class($model);
        }

        if (is_string($model) && class_exists($model)) {
            return $model;
        }

        // Sem model: tenta deduzir pela Policy concreta (ex.: ClientPolicy -> App\Models\Client).
        $policyName = class_basename(static::class);
        if ($policyName === 'DynamicPolicy') {
            return null;
        }
        $guess = 'App\\Models\\' . str_replace('Policy', '', $policyName);
        return class_exists($guess) ? $guess : null;
    }

    protected function getFeatureKeyFromModel($model): ?string
    {
        $class = $this->resolveModelClass($model);
        if (!$class) {
            return null;
        }

        // 1) Fonte única de verdade.
        if ($meta = SaaSRegistry::forModel($class)) {
            return $meta['feature'];
        }

        // 2) Fallback legado (Models ainda sem a trait).
        return match (Str::snake(class_basename($class))) {
            'role', 'permission' => 'tabela_roles',
            'financial', 'transaction' => 'modulo_financial',
            'chat', 'chat_room', 'message' => 'modulo_chat',
            default => null,
        };
    }

    protected function getPermissionName(string $action, $model): ?string
    {
        if (is_string($model) && (str_contains($model, 'Filament') || str_contains($model, 'Livewire'))) {
            return null;
        }

        $prefix = match ($action) {
            'viewAny', 'view' => 'ler',
            'create'          => 'criar',
            'update'          => 'editar',
            'delete'          => 'excluir',
            default           => $action
        };

        $class = $this->resolveModelClass($model);
        if (!$class) {
            return null;
        }

        // 1) Fonte única de verdade.
        if ($meta = SaaSRegistry::forModel($class)) {
            return "{$prefix}_{$meta['slug']}";
        }

        // 2) Fallback legado (Models ainda sem a trait).
        $slugName = Str::snake(class_basename($class));
        $map = [
            'role'        => 'funcao',
            'chat'        => 'chat',
            'chat_room'   => 'chat',
            'financial'   => 'financeiro',
        ];
        $suffix = $map[$slugName] ?? $slugName;

        return "{$prefix}_{$suffix}";
    }

    public function viewAny(User $user, $model = null): bool { return $this->check($user, 'viewAny', $model); }
    public function view(User $user, $model): bool { return $this->check($user, 'view', $model); }
    public function create(User $user, $model = null): bool { return $this->check($user, 'create', $model ?? static::class); }
    public function update(User $user, $model): bool { return $this->check($user, 'update', $model); }
    public function delete(User $user, $model): bool { return $this->check($user, 'delete', $model); }
}
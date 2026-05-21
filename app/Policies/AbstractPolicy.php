<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Str;

abstract class AbstractPolicy
{
    use HandlesAuthorization;

    /**
     * Valida se o objeto pertence ao mesmo tenant do usuário.
     */
    protected function isSameTenant(User $user, $model): bool
    {
        if (!isset($model->tenant_id)) return false;
        
        // 🔥 CORREÇÃO CRÍTICA: UUIDs são strings!
        // O (int) convertia tudo para 0 e quebrava o isolamento entre clientes.
        return (string) $user->tenant_id === (string) $model->tenant_id;
    }

    /**
     * Motor de autorização centralizado.
     */
    protected function check(User $user, string $action, $model = null): bool
    {
        // ⚠️ ATENÇÃO: Se o email do técnico terminar com @oravel.com.br, ele passa direto aqui!
        if ($user->isAdmin()) return true;

        $permission = $this->getPermissionName($action, $model);
        
        // Se não conseguiu resolver o nome da permissão (ex: ignorado), bloqueia por padrão.
        if (!$permission) return false;

        $hasPermission = $user->can($permission);

        if ($model && is_object($model) && $action !== 'create') {
            return $hasPermission && $this->isSameTenant($user, $model);
        }

        return $hasPermission;
    }

    /**
     * Resolve o nome da permissão mapeando a ação e o nome da tabela/modelo.
     */
    protected function getPermissionName(string $action, $model): ?string
    {
        // Ignora classes internas do Filament/Livewire
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

        $slugName = null;

        // Resolve o nome do modelo de forma dinâmica e segura
        if (is_object($model)) {
            $slugName = Str::snake(class_basename($model));
        } elseif (is_string($model) && class_exists($model)) {
            // O Filament passa a classe do Model no viewAny como string (Ex: 'App\Models\Asset')
            $slugName = Str::snake(class_basename($model));
        } else {
            $className = class_basename(static::class);
            // Previne falha caso a DynamicPolicy seja chamada sem contexto de modelo
            if ($className === 'DynamicPolicy') return null;

            $slugName = Str::snake(Str::singular($className));
            $slugName = str_replace('_policy', '', $slugName);
        }

        // 🔄 MAPEAMENTO REAL SINCRONIZADO COM O ROLE_RESOURCE
        $map = [
            'material_category' => 'categoria_material',
            'maintenance_order' => 'ordem_servico',
            'user'              => 'funcionario',
            'asset'             => 'ativo',
            'client'            => 'cliente',
            'contract'          => 'contrato',
            'checklist'         => 'checklist',
            'department'        => 'departamento',
            'material'          => 'material',
            'fleet_status'      => 'fila_logistica', // Ajuste conforme seu RoleResource
        ];

        $suffix = $map[$slugName] ?? $slugName;

        return "{$prefix}_{$suffix}";
    }

    // 🔥 CORREÇÃO: O Filament pode passar a string da classe no viewAny.
    // É necessário repassar a variável $model para a função check saber quem verificar.
    public function viewAny(User $user, $model = null): bool { return $this->check($user, 'viewAny', $model); }
    
    public function view(User $user, $model): bool { return $this->check($user, 'view', $model); }

    public function create(User $user): bool { return $this->check($user, 'create'); }

    public function update(User $user, $model): bool { return $this->check($user, 'update', $model); }

    public function delete(User $user, $model): bool { return $this->check($user, 'delete', $model); }
}
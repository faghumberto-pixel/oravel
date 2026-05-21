<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Str;

class SaaSResourcePolicy
{
    /**
     * Super Admin Bypass: Libera tudo para o Admin master.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Validador dinâmico que cruza o modelo atual com a matriz do Spatie.
     */
    private function checkPermission(User $user, string $ability, $recordOrModel): bool
    {
        // Descobre o nome do modelo (ex: App\Models\MaterialCategory -> MaterialCategory)
        $className = is_string($recordOrModel) ? $recordOrModel : get_class($recordOrModel);
        $baseModel = class_basename($className);

        // O seu dicionário exato de mapeamento
        $modulesMap = [
            'MaterialCategory' => 'material',
            'Material'         => 'material',
            'MaintenanceOrder' => 'ordem_servico',
            'User'             => 'usuario',
            'Asset'            => 'ativo',
            'Client'           => 'cliente',
            'Contract'         => 'contrato',
            'Role'             => 'funcionalidade',
            'Checklist'        => 'checklist',
        ];

        $moduleSlug = $modulesMap[$baseModel] ?? Str::snake(Str::singular($baseModel));

        // Mapeia o método do CRUD para o seu prefixo de Toggle
        $actionsMap = [
            'viewAny' => 'ler_',
            'view'    => 'ler_',
            'create'  => 'criar_',
            'update'  => 'editar_',
            'delete'  => 'excluir_',
        ];

        $prefix = $actionsMap[$ability] ?? 'ler_';
        $permissionName = $prefix . $moduleSlug;

        try {
            // Consulta de forma estrita a relação do Spatie no banco de dados
            return $user->hasPermissionTo($permissionName, 'web');
        } catch (\Throwable $e) {
            // Se a permissão não existir no banco, bloqueia por segurança máxima
            return false;
        }
    }

    public function viewAny(User $user): bool { return $this->checkPermission($user, 'viewAny', str_replace('Policy', '', class_basename($this))); }
    public function view(User $user, $record): bool { return $this->checkPermission($user, 'view', $record); }
    public function create(User $user): bool { return $this->checkPermission($user, 'create', str_replace('Policy', '', class_basename($this))); }
    public function update(User $user, $record): bool { return $this->checkPermission($user, 'update', $record); }
    public function delete(User $user, $record): bool { return $this->checkPermission($user, 'delete', $record); }
}
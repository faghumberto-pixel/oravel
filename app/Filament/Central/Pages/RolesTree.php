<?php

namespace App\Filament\Central\Pages;

use App\Filament\Central\Resources\RoleResource;
use App\Models\Role;
use App\Models\Tenant;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Árvore Tenant -> Perfis de Acesso (pedido do usuário 2026-09-27: "os
 * perfis de usuario tem que ser [organizados] como fizemos em arvore por
 * tenant, para que cada empresa possamos ver quantos perfis tem").
 *
 * RoleResource::table() lista TODOS os perfis de TODOS os tenants numa
 * tabela só, sem sequer mostrar a qual empresa cada um pertence (roles têm
 * nomes repetidos entre tenants -- unique constraint é
 * ['name','guard_name','tenant_id'], ver migration
 * fix_roles_unique_constraint_to_include_tenant_id) -- ficava impossível
 * saber quantos perfis uma empresa tinha só de olhar a lista. Esta página é
 * só visualização; editar/criar/excluir continua em RoleResource (link
 * direto por linha).
 */
class RolesTree extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Perfis por Tenant';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $title = 'Perfis por Tenant';

    protected static string $view = 'filament.central.pages.roles-tree';

    /**
     * @return Collection<int, array{tenant: Tenant, roles: Collection}>
     */
    public function getTenantTree(): Collection
    {
        return Tenant::query()
            ->orderBy('name')
            ->get()
            ->map(function (Tenant $tenant) {
                $roles = Role::withoutGlobalScopes()
                    ->withCount('permissions')
                    ->where('tenant_id', $tenant->id)
                    ->orderBy('name')
                    ->get();

                return [
                    'tenant' => $tenant,
                    'roles' => $roles,
                ];
            });
    }

    public function editUrl(Role $role): string
    {
        // Panel explícito -- sem isso, Resource::getUrl() pode resolver pro
        // panel errado fora do contexto de uma requisição já dentro do
        // Central (mesmo bug de resolução de panel documentado no caveat
        // do model Role em CLAUDE.md).
        return RoleResource::getUrl('edit', ['record' => $role], panel: 'central');
    }
}

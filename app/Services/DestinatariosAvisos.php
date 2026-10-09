<?php

namespace App\Services;

use App\Models\AvisoResponsavel;
use App\Models\Role;
use App\Models\User;
use App\Support\EventosAviso;
use Illuminate\Support\Collection;

/**
 * Descobre QUEM recebe um aviso numa empresa. Ordem:
 *  1. as pessoas que a empresa escolheu para esse aviso;
 *  2. senão, quem tem o papel que o sistema usava antes (ex.: "Comercial");
 *  3. senão, os administradores da empresa.
 * Nunca depende de a empresa ter um departamento ou papel específico.
 */
class DestinatariosAvisos
{
    /**
     * @return Collection<int, User>
     */
    public static function para(?string $tenantId, string $evento): Collection
    {
        if (blank($tenantId)) {
            return collect();
        }

        $escolhidos = static::usuarios($tenantId)
            ->whereIn('id', AvisoResponsavel::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('evento', $evento)->pluck('user_id'))
            ->get();

        if ($escolhidos->isNotEmpty()) {
            return $escolhidos;
        }

        $porPapel = collect();
        foreach (EventosAviso::todos()[$evento]['papeis'] ?? [] as $papel) {
            $porPapel = $porPapel->merge(static::comPapel($tenantId, $papel));
        }

        if ($porPapel->isNotEmpty()) {
            return $porPapel->unique('id')->values();
        }

        return static::comPapel($tenantId, 'admin');
    }

    /**
     * Mesmo cuidado de sempre: o Spatie resolve papel por nome em todas as
     * empresas, então acha o papel da empresa primeiro e passa a instância.
     *
     * @return Collection<int, User>
     */
    private static function comPapel(string $tenantId, string $papel): Collection
    {
        $role = Role::where('name', $papel)->where('guard_name', 'web')->where('tenant_id', $tenantId)->first();

        if (! $role) {
            return collect();
        }

        return static::usuarios($tenantId)->get()->filter(fn (User $u) => $u->hasRole($role))->values();
    }

    private static function usuarios(string $tenantId)
    {
        return User::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_approved', true);
    }
}

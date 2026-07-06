<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

class Tenancy
{
    /**
     * Tenant do usuario logado. Substitui Filament::getTenant(),
     * que e sempre null neste painel (nao usamos tenancy nativa).
     * Retorna null para console e nao autenticado.
     *
     * Super admin nao tem tenant proprio (por isso nunca conseguia criar
     * nenhum registro por tenant -- BelongsToTenant nao tinha de onde tirar
     * o tenant_id). Ele pode escolher um tenant "atuante" (sessao
     * acting_tenant_id, via SelectActingTenant) para fins de CRIACAO de
     * registros -- isso nao afeta o bypass de LEITURA (super admin sempre
     * ve tudo, em qualquer tenant, com ou sem essa escolha).
     */
    public static function current(): ?Tenant
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            $actingTenantId = session('acting_tenant_id');

            return $actingTenantId ? Tenant::find($actingTenantId) : null;
        }

        return $user->tenant;
    }
}

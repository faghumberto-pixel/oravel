<?php

namespace App\Support;

use App\Models\Tenant;
use Filament\Facades\Filament;
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
     *
     * ATUALIZADO 05/10/2026 (pedido do usuario): no painel de cliente (admin)
     * a escolha tambem FILTRA a leitura -- ver actingReadScopeTenantId().
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

    /**
     * Tenant pelo qual a LEITURA do super admin deve ser filtrada: so quando
     * ele escolheu um tenant atuante E esta no painel de cliente (admin).
     * Sem escolha ("ver todos") ou no painel Central devolve null = sem
     * filtro (console/jobs nao tem usuario logado, entao nem chegam aqui). Para qualquer outro
     * usuario devolve null (o filtro deles e' o proprio tenant_id).
     */
    public static function actingReadScopeTenantId(): ?string
    {
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'isSuperAdmin') || ! $user->isSuperAdmin()) {
            return null;
        }

        $actingTenantId = session('acting_tenant_id');

        if (blank($actingTenantId) || Filament::getCurrentPanel()?->getId() !== 'admin') {
            return null;
        }

        return (string) $actingTenantId;
    }
}

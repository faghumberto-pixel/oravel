<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

class Tenancy
{
    /**
     * Tenant do usuario logado. Substitui Filament::getTenant(),
     * que e sempre null neste painel (nao usamos tenancy nativa).
     * Retorna null para super admin, console e nao autenticado.
     */
    public static function current(): ?Tenant
    {
        return Auth::user()?->tenant;
    }
}

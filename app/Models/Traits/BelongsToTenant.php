<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        // Aplica o Global Scope para todas as consultas.
        static::addGlobalScope(new TenantScope());

        // Observer 'creating' com proteção para Console
        static::creating(function ($model) {
            // Se estivermos rodando via console (Artisan), não injetamos automaticamente
            // para evitar conflitos. O desenvolvedor deve definir o tenant_id manualmente
            // ou deixar que o processo de importação/comando defina o valor.
            if (app()->runningInConsole()) {
                return;
            }

            if (session()->has('tenant_id')) {
                $model->tenant_id = session('tenant_id');
            } elseif (Auth::check()) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
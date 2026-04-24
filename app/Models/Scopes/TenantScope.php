<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // 1. Em comandos Artisan ou Migrations, nunca filtrar.
        if (App::runningInConsole()) {
            return;
        }

        // 2. CRÍTICO: Se for Filament/Livewire (requisição interna), 
        // ou se não tivermos sessão ainda, saia imediatamente.
        if (Request::hasHeader('x-livewire') || !Request::hasSession() || !session()->has('tenant_id')) {
            return;
        }

        // 3. Aplica o filtro de segurança somente se tudo estiver validado.
        $builder->where('tenant_id', session('tenant_id'));
    }
}
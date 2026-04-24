<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;

class BaseModel extends Model
{
    /**
     * O booted é o momento ideal para aplicar o escopo de segurança.
     * Estamos tornando este escopo "inteligente" para não travar o Filament.
     */
    protected static function booted()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            
            // 1. Ignorar se estiver rodando via terminal (Artisan, Migrations, Jobs)
            if (App::runningInConsole()) {
                return;
            }

            // 2. Ignorar se for uma requisição do Filament ou Livewire.
            // Isso previne o loop infinito de recursão que estava travando o servidor.
            if (Request::hasHeader('x-livewire')) {
                return;
            }

            // 3. Só aplica o filtro se a sessão estiver 100% carregada e contiver o tenant
            if (!Request::hasSession() || !session()->has('tenant_id')) {
                return;
            }

            // 4. Aplicação segura do filtro de tenant
            $builder->where('tenant_id', session('tenant_id'));
        });
    }
}
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate; // Importante
use App\Models\Contract;
use App\Observers\ContractObserver;
use Spatie\Permission\Models\Role;
use App\Models\Department;
use App\Policies\DynamicPolicy; // Importar a DynamicPolicy

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // ATIVAÇÃO FORÇADA
        Contract::observe(ContractObserver::class);

        // INJEÇÃO CRUCIAL: Vincula dinamicamente a tabela de roles
        Role::resolveRelationUsing('department', function ($roleModel) {
            return $roleModel->belongsTo(Department::class, 'department_id');
        });

        /**
         * PORTEIRO UNIVERSAL: 
         * Se o modelo não tiver uma Policy explícita (ex: AssetPolicy),
         * o Laravel redireciona a autorização para a DynamicPolicy.
         */
        Gate::guessPolicyNamesUsing(function ($modelClass) {
            $policy = 'App\\Policies\\' . class_basename($modelClass) . 'Policy';

            return class_exists($policy) ? $policy : DynamicPolicy::class;
        });
    }
}
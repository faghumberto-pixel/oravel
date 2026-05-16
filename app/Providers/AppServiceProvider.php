<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Models\Contract;
use App\Observers\ContractObserver;
use Spatie\Permission\Models\Role;
use App\Models\Department;

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

        // INJEÇÃO CRUCIAL: Vincula dinamicamente a tabela de roles do Spatie aos Departamentos do Oravel
        Role::resolveRelationUsing('department', function ($roleModel) {
            return $roleModel->belongsTo(Department::class, 'department_id');
        });
    }
}
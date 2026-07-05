<?php

namespace App\Providers;

use App\Models\Contract;
use App\Models\Department;
use App\Models\EquipmentDamage;
use App\Models\EquipmentMovement; // Importante
use App\Models\FleetTollRecord;
use App\Models\FreightRecord;
use App\Observers\ContractObserver;
use App\Observers\EquipmentDamageObserver;
use App\Observers\EquipmentMovementObserver;
use App\Observers\FleetTollRecordObserver;
use App\Observers\FreightRecordObserver;
use App\Policies\DynamicPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role; // Importar a DynamicPolicy

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
        EquipmentMovement::observe(EquipmentMovementObserver::class);
        EquipmentDamage::observe(EquipmentDamageObserver::class);
        FreightRecord::observe(FreightRecordObserver::class);
        FleetTollRecord::observe(FleetTollRecordObserver::class);

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
            $policy = 'App\\Policies\\'.class_basename($modelClass).'Policy';

            return class_exists($policy) ? $policy : DynamicPolicy::class;
        });
    }
}

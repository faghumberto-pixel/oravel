<?php

namespace App\Providers;

use App\Models\Contract;
use App\Models\Department;
use App\Models\EquipmentDamage;
use App\Models\EquipmentMovement; // Importante
use App\Models\FleetTollRecord;
use App\Models\FreightRecord;
use App\Models\MaintenanceOrder;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Observers\ContractObserver;
use App\Observers\EquipmentDamageObserver;
use App\Observers\EquipmentMovementObserver;
use App\Observers\FleetTollRecordObserver;
use App\Observers\FreightRecordObserver;
use App\Observers\MaintenanceOrderChecklistSnapshotObserver;
use App\Policies\DynamicPolicy;
use App\Support\Tenancy;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

 // Importar a DynamicPolicy

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
        MaintenanceOrder::observe(MaintenanceOrderChecklistSnapshotObserver::class);

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

        $this->registerActivityLogging();
    }

    /**
     * Log de "o que o usuario fez" (criar/editar/excluir), complementar ao
     * de navegacao (LogUserActivity middleware, so GET). Escuta os eventos
     * curinga do Eloquent em vez de tocar em cada Model -- so grava quando
     * ha um usuario autenticado real (console/seeder fica de fora) e o
     * model tocado pertence a um tenant (tem tenant_id), pra nao logar
     * ruido de tabelas de sistema (sessions, cache, etc.).
     */
    private function registerActivityLogging(): void
    {
        foreach (['created', 'updated', 'deleted'] as $action) {
            Event::listen("eloquent.{$action}: *", function (string $eventName, array $payload) use ($action) {
                $this->logModelMutation($action, $payload[0] ?? null);
            });
        }

        Event::listen(Login::class, function (Login $event) {
            $this->logAuthEvent(UserActivityLog::ACTION_LOGIN, $event->user);
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                $this->logAuthEvent(UserActivityLog::ACTION_LOGOUT, $event->user);
            }
        });
    }

    private function logModelMutation(string $action, mixed $model): void
    {
        if (! $model instanceof Model || $model instanceof UserActivityLog) {
            return;
        }

        $user = Auth::user();
        $tenant = Tenancy::current();

        if (! $user || ! $tenant || blank($model->tenant_id ?? null)) {
            return;
        }

        UserActivityLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'method' => strtoupper(substr($action, 0, 3)),
            'path' => request()?->path() ?? '',
            'route_name' => request()?->route()?->getName(),
            'resource_label' => class_basename($model),
            'action' => $action,
            'subject_type' => get_class($model),
            'subject_id' => (string) $model->getKey(),
        ]);
    }

    private function logAuthEvent(string $action, mixed $user): void
    {
        if (! $user instanceof User || blank($user->tenant_id)) {
            return;
        }

        UserActivityLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'method' => 'POST',
            'path' => request()?->path() ?? '',
            'action' => $action,
            'resource_label' => $action === UserActivityLog::ACTION_LOGIN ? 'Login' : 'Logout',
        ]);
    }
}

<?php
namespace App\Traits;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;

trait HasPlanAuthorization
{
    /**
     * Controla o acesso à rota/página (HTTP + injeção por URL).
     * Checa a feature comercial do plano E a permissão individual.
     */
    public static function canAccess(): bool
    {
        return static::passaFeatureDoPlano() && static::passaPermissaoDoUsuario('viewAny');
    }

    /**
     * Controla a visibilidade do item na Sidebar.
     * Mesma regra do canAccess: feature do plano + permissão do usuário.
     */
    public static function canViewAny(): bool
    {
        return static::passaFeatureDoPlano() && static::passaPermissaoDoUsuario('viewAny');
    }

    public static function canCreate(): bool
    {
        return static::passaFeatureDoPlano() && static::passaPermissaoDoUsuario('create');
    }

    /**
     * TRAVA COMERCIAL: o plano do tenant precisa ter a feature da tabela.
     */
    protected static function passaFeatureDoPlano(): bool
    {
        $tenant = \App\Support\Tenancy::current();

        // Painel Central (sem tenant): libera.
        if (!$tenant) {
            return true;
        }

        $user = auth()->user();
        if ($user && method_exists($user, 'isOravelSuperAdmin') && $user->isOravelSuperAdmin()) {
            return true;
        }

        $modelClass = static::getModel();
        if (class_exists($modelClass)) {
            $tableName = (new $modelClass)->getTable();
            return $tenant->hasFeature('tabela_' . $tableName);
        }

        return true;
    }

    /**
     * TRAVA DE PERMISSÃO: delega ao Gate, que cai na DynamicPolicy/AbstractPolicy
     * (fonte única de verdade via SaaSRegistry). SuperAdmin/Admin já passam lá dentro.
     */
    protected static function passaPermissaoDoUsuario(string $action): bool
    {
        // Fora de tenant (Central) não aplica trava de permissão por módulo.
        if (!\App\Support\Tenancy::current()) {
            return true;
        }

        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return Gate::forUser($user)->check($action, static::getModel());
    }
}
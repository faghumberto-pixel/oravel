<?php

return [

    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role' => App\Models\Role::class, 
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        'role_pivot_key' => null,
        'permission_pivot_key' => null,
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'tenant_id', // Isso é crucial para o seu Multi-tenancy
    ],

    /*
    |--------------------------------------------------------------------------
    | Teams (Tenants)
    |--------------------------------------------------------------------------
    | Ativar 'teams' faz com que o Spatie exija um ID de tenant em todas as 
    | checagens de permissão.
    */
    'teams' => false, 

    'register_permission_check_method' => true,
    'register_octane_reset_listener' => false,
    
    'display_permission_in_exception' => false,
    'display_role_in_exception' => false,
    'enable_wildcard_permission' => false,

    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],
];
<?php

namespace App\Services;

use App\Models\Tenant;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class TenantProvisioner
{
    // A variável $tenant agora está declarada corretamente aqui
    public static function provision(Tenant $tenant)
    {
        $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);

        $resources = ['Asset', 'Client', 'Material', 'MaintenanceOrder', 'ChecklistTemplate', 'Supplier'];

        foreach ($resources as $res) {
            $perm = Permission::firstOrCreate(['name' => "view_any_{$res}", 'guard_name' => 'web']);
            
            DB::table('model_has_permissions')->updateOrInsert(
                [
                    'permission_id' => $perm->id, 
                    'model_type' => 'Spatie\Permission\Models\Role', 
                    'model_id' => $role->id, 
                    'tenant_id' => $tenant->id
                ]
            );
        }
    }
}

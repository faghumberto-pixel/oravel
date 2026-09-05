<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->boot();

use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Auth;

// Simular login como admin@demo1.com.br
$user = User::where('email', 'admin@demo1.com.br')->first();

if (!$user) {
    echo "User not found: admin@demo1.com.br\n";
    exit(1);
}

// Fazer login
Auth::login($user);

echo "=== DEBUG: Painel de Controle Access Check ===\n\n";
echo "User: " . $user->name . " (" . $user->email . ")\n";
echo "User ID: " . $user->id . "\n";
echo "User Tenant ID: " . $user->tenant_id . "\n";
echo "User is_admin(): " . ($user->isAdmin() ? 'true' : 'false') . "\n";
echo "User isSuperAdmin(): " . ($user->isSuperAdmin() ? 'true' : 'false') . "\n";

$supervised = $user->supervisedDepartmentIds();
echo "User supervisedDepartmentIds(): " . json_encode($supervised) . "\n";

// Check condition 1: linha 37-38 do PainelGestao
$shouldBlockLine37 = !$user->isAdmin() && empty($supervised);
echo "\nLine 37 condition (!isAdmin && empty(supervisedDepartmentIds)): " . ($shouldBlockLine37 ? 'WOULD BLOCK' : 'PASS') . "\n";

// Get tenant
$tenant = $user->tenant;
echo "\nTenant: " . ($tenant ? $tenant->name : 'NULL') . "\n";
echo "Tenant ID: " . ($tenant ? $tenant->id : 'NULL') . "\n";

if ($tenant) {
    echo "Tenant plan_id: " . ($tenant->plan_id ?: 'NULL') . "\n";
    echo "Tenant features (JSON): " . json_encode($tenant->features) . "\n";
    
    $plan = $tenant->plan;
    echo "Plan: " . ($plan ? $plan->name : 'NULL') . "\n";
    
    if ($plan) {
        echo "Plan ID: " . $plan->id . "\n";
        echo "Plan features: " . json_encode($plan->features) . "\n";
        echo "Plan hasFeature('modulo_dashboard'): " . ($plan->hasFeature('modulo_dashboard') ? 'true' : 'false') . "\n";
    }
    
    echo "Tenant hasFeature('modulo_dashboard'): " . ($tenant->hasFeature('modulo_dashboard') ? 'true' : 'false') . "\n";
}

// Full canAccess logic simulation
echo "\n=== FULL canAccess() SIMULATION ===\n";

$user = auth()->user();
if ($user && ! $user->isAdmin() && empty($user->supervisedDepartmentIds())) {
    echo "BLOCKED at line 37-38: tecnico puro (not admin, no supervised departments)\n";
    exit;
}

$tenant = Tenancy::current();
if (! $tenant) {
    echo "No tenant context (super admin without tenant): PASS\n";
    exit;
}

$hasFeature = $tenant->hasFeature('modulo_dashboard');
echo "Tenant->hasFeature('modulo_dashboard'): " . ($hasFeature ? 'true' : 'false') . "\n";
echo "canAccess() result: " . ($hasFeature ? 'PASS' : 'BLOCKED') . "\n";


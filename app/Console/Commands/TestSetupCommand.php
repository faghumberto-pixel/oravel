// Conteúdo de app/Console/Commands/TestSetupCommand.php
<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Asset;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TestSetupCommand extends Command
{
    protected $signature = 'test:setup';
    protected $description = 'Executa o script de setup e teste para multi-tenancy e Spatie.';
public function handle()
{
    $this->info("Iniciando script de setup e teste...");
// --- 1. Criar Tenants (se ainda não existirem) ---
$tenant1 = Tenant::where('slug', 'tenant-a')->first();
if (!$tenant1) {
    $tenant1 = Tenant::create(['name' =&amp;gt; 'Tenant A', 'slug' =&amp;gt; 'tenant-a']);
    $this->info("Tenant A created: " . $tenant1->name);
} else {
    $this->info("Tenant A already exists: " . $tenant1->name);
}

$tenant2 = Tenant::where('slug', 'tenant-b')->first();
if (!$tenant2) {
    $tenant2 = Tenant::create(['name' =&amp;gt; 'Tenant B', 'slug' =&amp;gt; 'tenant-b']);
    $this->info("Tenant B created: " . $tenant2->name);
} else {
    $this->info("Tenant B already exists: " . $tenant2->name);
}

// --- 2. Criar Usuários Associados a Tenants ---
// Crie um usuário para o Tenant A
$userA = User::where('email', 'user.a@tenant-a.com')->first();
if (!$userA) {
    $userA = User::factory()->create([
        'name' =&amp;gt; 'User A',
        'email' =&amp;gt; 'user.a@tenant-a.com',
        'password' =&amp;gt; bcrypt('password'),
        'tenant_id' =&amp;gt; $tenant1->id,
        'job_title' =&amp;gt; 'gestor',
    ]);
    $this->info("User A created: " . $userA->email . " for Tenant: " . $userA->tenant->name . " with job_title: " . $userA->job_title);
} else {
    $this->info("User A already exists: " . $userA->email);
}

// Crie um usuário para o Tenant B
$userB = User::where('email', 'user.b@tenant-b.com')->first();
if (!$userB) {
    $userB = User::factory()->create([
        'name' =&amp;gt; 'User B',
        'email' =&amp;gt; 'user.b@tenant-b.com',
        'password' =&amp;gt; bcrypt('password'),
        'tenant_id' =&amp;gt; $tenant2->id,
        'job_title' =&amp;gt; 'colaborador',
    ]);
    $this->info("User B created: " . $userB->email . " for Tenant: " . $userB->tenant->name . " with job_title: " . $userB->job_title);
} else {
    $this->info("User B already exists: " . $userB->email);
}

// Crie um usuário Admin para o Tenant A (para testes de Spatie)
$userAdminTenantA = User::where('email', 'admin.a@tenant-a.com')->first();
if (!$userAdminTenantA) {
    $userAdminTenantA = User::factory()->create([
        'name' =&amp;gt; 'Admin Tenant A',
        'email' =&amp;gt; 'admin.a@tenant-a.com',
        'password' =&amp;gt; bcrypt('password'),
        'tenant_id' =&amp;gt; $tenant1->id,
        'job_title' =&amp;gt; 'gestor',
    ]);
    $this->info("Admin Tenant A created: " . $userAdminTenantA->email);
} else {
    $this->info("Admin Tenant A already exists: " . $userAdminTenantA->email);
}

// Crie um usuário Colaborador para o Tenant A (para testes de Spatie)
$userColaboradorTenantA = User::where('email', 'colab.a@tenant-a.com')->first();
if (!$userColaboradorTenantA) {
    $userColaboradorTenantA = User::factory()->create([
        'name' =&amp;gt; 'Colaborador Tenant A',
        'email' =&amp;gt; 'colab.a@tenant-a.com',
        'password' =&amp;gt; bcrypt('password'),
        'tenant_id' =&amp;gt; $tenant1->id,
        'job_title' =&amp;gt; 'analista',
    ]);
    $this->info("Colaborador Tenant A created: " . $userColaboradorTenantA->email);
} else {
    $this->info("Colaborador Tenant A already exists: " . $userColaboradorTenantA->email);
}

// Crie o Oravel Admin (global)
$oravelAdmin = User::where('email', 'admin@oravel.com')->first();
if (!$oravelAdmin) {
    $oravelAdmin = User::factory()->create([
        'name' =&amp;gt; 'Oravel Admin',
        'email' =&amp;gt; 'admin@oravel.com',
        'password' =&amp;gt; bcrypt('password'),
        'tenant_id' =&amp;gt; null, // Admin global não tem tenant_id
        'job_title' =&amp;gt; 'oravel_admin',
    ]);
    $this->info("Oravel Admin created: " . $oravelAdmin->email);
} else {
    $this->info("Oravel Admin already exists: " . $oravelAdmin->email);
}

// --- 3. Criar Assets (Assets) ---
// Simule o login do User A para criar Assets para o Tenant A
Auth::login($userA);
$this->info("\nLogged in as: " . Auth::user()->name . " (Tenant: " . Auth::user()->tenant->name . ") to create assets.");

// Crie Assets para o Tenant A
$assetA1 = Asset::firstOrCreate(['name' =&amp;gt; 'Máquina X', 'tenant_id' =&amp;gt; Auth::user()->tenant_id], ['description' =&amp;gt; 'Asset do Tenant A']);
$assetA2 = Asset::firstOrCreate(['name' =&amp;gt; 'Ferramenta Y', 'tenant_id' =&amp;gt; Auth::user()->tenant_id], ['description' =&amp;gt; 'Outro Asset do Tenant A']);
$this->info("Created Asset A1 (ID: " . $assetA1->id . ", Tenant ID: " . $assetA1->tenant_id . ")");
$this->info("Created Asset A2 (ID: " . $assetA2->id . ", Tenant ID: " . $assetA2->tenant_id . ")");

// Simule o login do User B para criar Assets para o Tenant B
Auth::login($userB);
$this->info("Logged in as: " . Auth::user()->name . " (Tenant: " . Auth::user()->tenant->name . ") to create assets.");

// Crie um Asset para o User B (Tenant B)
$assetB1 = Asset::firstOrCreate(['name' =&amp;gt; 'Veículo Z', 'tenant_id' =&amp;gt; Auth::user()->tenant_id], ['description' =&amp;gt; 'Asset do Tenant B']);
$this->info("Created Asset B1 (ID: " . $assetB1->id . ", Tenant ID: " . $assetB1->tenant_id . ")");

// --- 4. Criar e Atribuir Permissões e Roles (Spatie) ---
$this->info("\n--- Setting up Spatie Roles and Permissions ---");

// Simular login de um usuário do Tenant A para que as roles/permissions sejam criadas com o tenant_id correto
Auth::login($userAdminTenantA);
$this->info("Logged in as: " . Auth::user()->name . " for Spatie setup.");

// Criar as Permissões
$permissions = [
    'os.ver', 'os.criar', 'os.editar', 'os.analisar', 'os.aprovar', 'os.encerrar',
    'servico.aprovar', 'servico.executar',
    'material.solicitar', 'material.autorizar_compra',
    'Asset.ver', 'Asset.gerenciar',
];

foreach ($permissions as $permissionName) {
    Permission::firstOrCreate(['name' =&amp;gt; $permissionName, 'tenant_id' =&amp;gt; Auth::user()->tenant_id]);
}
$this->info("Permissions created/ensured for Tenant A.");

// Criar as Roles de Alto Nível
$roleGestor = Role::firstOrCreate(['name' =&amp;gt; 'gestor', 'tenant_id' =&amp;gt; Auth::user()->tenant_id]);
$roleColaborador = Role::firstOrCreate(['name' =&amp;gt; 'colaborador', 'tenant_id' =&amp;gt; Auth::user()->tenant_id]);
$this->info("Roles 'gestor' and 'colaborador' created/ensured for Tenant A.");

// Atribuir Permissões às Roles
$roleGestor->givePermissionTo([
    'os.ver', 'os.criar', 'os.editar', 'os.analisar', 'os.aprovar', 'os.encerrar',
    'servico.aprovar',
    'material.solicitar', 'material.autorizar_compra',
    'Asset.ver', 'Asset.gerenciar',
]);
$this->info("Permissions assigned to 'gestor' role for Tenant A.");

$roleColaborador->givePermissionTo([
    'os.ver', 'os.criar',
    'servico.executar',
    'material.solicitar',
    'Asset.ver',
]);
$this->info("Permissions assigned to 'colaborador' role for Tenant A.");

// Criar a role 'oravel_admin' (global, sem tenant_id)
$roleOravelAdmin = Role::firstOrCreate(['name' =&amp;gt; 'oravel_admin', 'tenant_id' =&amp;gt; null]);
$oravelAdmin->assignRole('oravel_admin');
$this->info("Role 'oravel_admin' created and assigned to " . $oravelAdmin->name . ".");


// --- 5. Testar Permissões e Scopes ---
$this->info("\n--- Testing Permissions and Scopes ---");

// Teste Oravel Admin
Auth::login($oravelAdmin);
$this->info("Logged in as: " . Auth::user()->name . " (Role: " . implode(', ', Auth::user()->getRoleNames()->toArray()) . ")");
$allAssets = Asset::all(); // Deve retornar TODOS os Assets para o oravel_admin
$this->info("Total assets visible to Oravel Admin: " . $allAssets->count()); // Deve ser 3 (2 do Tenant A + 1 do Tenant B)
$this->info("Oravel Admin can 'os.aprovar'? " . (Auth::user()->can('os.aprovar') ? 'Yes' : 'No')); // Deve ser 'Yes'
$this->info("Oravel Admin can 'Asset.gerenciar'? " . (Auth::user()->can('Asset.gerenciar') ? 'Yes' : 'No')); // Deve ser 'Yes'
$this->info("Oravel Admin can 'some.nonexistent.permission'? " . (Auth::user()->can('some.nonexistent.permission') ? 'Yes' : 'No')); // Deve ser 'Yes'

// Teste Admin Tenant A
Auth::login($userAdminTenantA);
$this->info("\nLogged in as: " . Auth::user()->name . " (Role: " . implode(', ', Auth::user()->getRoleNames()->toArray()) . ")");
$assetsUserAdminA = Asset::all(); // Deve retornar apenas os Assets do Tenant A
$this->info("Assets visible to Admin Tenant A: " . $assetsUserAdminA->count()); // Deve ser 2
$this->info("Admin Tenant A can 'os.aprovar'? " . (Auth::user()->can('os.aprovar') ? 'Yes' : 'No')); // Deve ser 'Yes'
$this->info("Admin Tenant A can 'servico.executar'? " . (Auth::user()->can('servico.executar') ? 'Yes' : 'No')); // Deve ser 'No'

// Teste Colaborador Tenant A
Auth::login($userColaboradorTenantA);
$this->info("\nLogged in as: " . Auth::user()->name . " (Role: " . implode(', ', Auth::user()->getRoleNames()->toArray()) . ")");
$assetsUserColabA = Asset::all(); // Deve retornar apenas os Assets do Tenant A
$this->info("Assets visible to Colaborador Tenant A: " . $assetsUserColabA->count()); // Deve ser 2
$this->info("Colaborador Tenant A can 'os.aprovar'? " . (Auth::user()->can('os.aprovar') ? 'Yes' : 'No')); // Deve ser 'No'
$this->info("Colaborador Tenant A can 'os.criar'? " . (Auth::user()->can('os.criar') ? 'Yes' : 'No')); // Deve ser 'Yes'

// Teste User B (Colaborador Tenant B)
Auth::login($userB);
$this->info("\nLogged in as: " . Auth::user()->name . " (Role: " . implode(', ', Auth::user()->getRoleNames()->toArray()) . ")");
$assetsUserB = Asset::all(); // Deve retornar apenas os Assets do Tenant B
$this->info("Assets visible to User B: " . $assetsUserB->count()); // Deve ser 1
$this->info("User B can 'os.aprovar'? " . (Auth::user()->can('os.aprovar') ? 'Yes' : 'No')); // Deve ser 'No'
$this->info("User B can 'os.criar'? " . (Auth::user()->can('os.criar') ? 'Yes' : 'No')); // Deve ser 'Yes'

$this->info("\nScript de setup e teste concluído!");
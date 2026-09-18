<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "=== DIAGNÓSTICO DO PORTAL DO CLIENTE ===\n\n";

// 1. Listar TODOS os tenants
echo "1. TODOS OS TENANTS:\n";
$all_tenants = DB::table('tenants')->get(['id', 'slug', 'name']);
if ($all_tenants->isEmpty()) {
    echo "❌ Nenhum tenant encontrado!\n";
    exit(1);
} else {
    foreach ($all_tenants as $t) {
        echo "  - {$t->slug} (ID: {$t->id}) - {$t->name}\n";
    }
}

// 2. Usar primeiro tenant para criar cliente
$first = $all_tenants->first();
echo "\n2. USANDO TENANT: {$first->slug}\n";

$email = "admin@{$first->slug}.com.br";
echo "3. CRIANDO/VERIFICANDO CLIENTE: {$email}\n";

DB::table('clients')->updateOrInsert(
    ['email' => $email],
    [
        'tenant_id' => $first->id,
        'name' => 'Admin ' . $first->name,
        'password' => Hash::make('TestDemo2026'),
        'portal_access_enabled_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]
);
echo "✅ Cliente salvo!\n";

// Verificar se realmente foi criado
$client = DB::table('clients')->where('email', $email)->first();
if ($client) {
    echo "\n4. CLIENTE VERIFICADO:\n";
    echo "   Email: {$client->email}\n";
    echo "   Tenant ID: {$client->tenant_id}\n";
    echo "   Portal habilitado: " . ($client->portal_access_enabled_at ? 'SIM' : 'NÃO') . "\n";

    if (Hash::check('TestDemo2026', $client->password)) {
        echo "   Senha: CORRETA (TestDemo2026)\n";
    } else {
        echo "   Senha: ❌ INCORRETA!\n";
    }
} else {
    echo "❌ ERRO: Cliente não foi salvo!\n";
}

echo "\n✅ Diagnóstico concluído!\n";

<?php
// Script para criar clientes de demo
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;

$tenants = Tenant::where('slug', 'LIKE', 'demo%')->get();

foreach ($tenants as $tenant) {
    $email = 'admin@' . $tenant->slug . '.com.br';

    $client = Client::updateOrCreate(
        ['email' => $email],
        [
            'tenant_id' => $tenant->id,
            'name' => 'Admin ' . $tenant->name,
            'password' => Hash::make('TestDemo2026'),
            'portal_access_enabled_at' => now(),
        ]
    );

    echo "✅ Cliente criado: {$email}\n";
}

echo "\n✅ Clientes de demo criados com sucesso!\n";

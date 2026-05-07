<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // Limpeza dos dados
        Asset::query()->delete();
        User::query()->delete();
        Tenant::query()->delete();

        $tenantsData = [
            [
                'name' => 'Campinas Tech Solutions', 
                'slug' => 'campinas-tech',
                'admin' => ['name' => 'Gestor Tech', 'email' => 'admin@campinastech.com'],
                'assets' => [
                    ['name' => 'Servidor Dell R740', 'patrimonio' => 'SVR-CT-001', 'status' => 'ativo'],
                    ['name' => 'Osciloscópio Digital', 'patrimonio' => 'OSC-CT-002', 'status' => 'ativo'],
                ]
            ],
            [
                'name' => 'Vira-Virou Logística', 
                'slug' => 'vira-virou-log',
                'admin' => ['name' => 'Gestor Log', 'email' => 'admin@viravirou.com'],
                'assets' => [
                    ['name' => 'Empilhadeira Elétrica', 'patrimonio' => 'EMP-VL-101', 'status' => 'ativo'],
                    ['name' => 'Paleteira Hidráulica', 'patrimonio' => 'PAL-VL-102', 'status' => 'manutenção'],
                ]
            ],
        ];

        foreach ($tenantsData as $data) {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
            ]);

            User::create([
                'name' => $data['admin']['name'],
                'email' => $data['admin']['email'],
                'password' => Hash::make('password123'),
                'tenant_id' => $tenant->id,
                'email_verified_at' => now(),
            ]);

            foreach ($data['assets'] as $assetData) {
                Asset::create([
                    'name' => $assetData['name'],
                    'description' => 'Ativo da unidade ' . $data['name'],
                    'tag' => $assetData['patrimonio'], // Obrigatório
                    'patrimonio' => $assetData['patrimonio'],
                    'status' => $assetData['status'],
                    'criticality' => 'medium', // Valor padrão obrigatório para o banco
                    'tenant_id' => $tenant->id,
                ]);
            }
        }

        $this->command->info('Tenants de Campinas criados com sucesso!');
    }
}
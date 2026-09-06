<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\DocumentSignature;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder para popular dados de teste do módulo de Assinatura Eletrônica.
 *
 * Uso: php artisan db:seed --class=ContractSignatureTestSeeder
 *
 * Ou para um tenant específico via tinker:
 * $tenant = Tenant::first();
 * $this->call(ContractSignatureTestSeeder::class, ['--tenant-id' => $tenant->id]);
 */
class ContractSignatureTestSeeder extends Seeder
{
    public function run(): void
    {
        // Encontra o primeiro Tenant para teste
        $tenant = Tenant::firstWhere('segment', 'torres-guindastes')
            ?? Tenant::first();

        if (!$tenant) {
            $this->command->error('Nenhum Tenant encontrado. Execute o seeder de Tenants primeiro.');
            return;
        }

        $this->command->info("Usando Tenant: {$tenant->name} ({$tenant->id})");

        // Encontra ou cria clientes de teste
        $clients = Client::where('tenant_id', $tenant->id)
            ->limit(2)
            ->get();

        if ($clients->isEmpty()) {
            $this->command->warn('Nenhum Cliente encontrado. Criando clientes de teste...');
            $clients = collect(range(1, 2))->map(function ($i) use ($tenant) {
                return Client::create([
                    'tenant_id' => $tenant->id,
                    'name' => "Cliente Teste {$i}",
                    'cpf_cnpj' => fake()->numerify('##.###.###/####-##'),
                    'address' => fake()->streetAddress(),
                    'cep' => fake()->numerify('##.###-###'),
                    'city' => fake()->city(),
                    'uf' => fake()->randomElement(['SP', 'RJ', 'MG', 'BA', 'RS']),
                    'contact_name' => fake()->name(),
                    'email' => fake()->email(),
                    'phone' => fake()->phoneNumber(),
                ]);
            });
        }

        // Encontra ou cria ativos de teste
        $assets = Asset::where('tenant_id', $tenant->id)
            ->limit(3)
            ->get();

        if ($assets->isEmpty()) {
            $this->command->warn('Nenhum Ativo encontrado. Criando ativos de teste...');
            $assets = collect(range(1, 3))->map(function ($i) use ($tenant) {
                return Asset::create([
                    'tenant_id' => $tenant->id,
                    'name' => "Equipamento Teste {$i}",
                    'tag' => 'EQP-'.Str::random(8).'-'.$i,
                    'patrimonio' => 'PAT-'.fake()->numerify('######'),
                    'description' => fake()->sentence(),
                    'serial_number' => Str::random(12),
                    'status' => fake()->randomElement(['Ativo', 'Inativo', 'Em Manutenção']),
                    'criticality' => fake()->randomElement(['low', 'medium', 'high']),
                ]);
            });
        }

        $this->command->info("Usando {$clients->count()} clientes e {$assets->count()} ativos");

        // Cria 3 contratos com diferentes estados de assinatura
        $this->command->info('Criando contratos com assinaturas...');

        // 1. Contrato com assinatura PENDENTE (não assinado)
        $contract1 = Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $clients->get(0)->id,
            'asset_id' => $assets->get(0)->id,
            'contract_number' => 'CTR-SIG-'.Str::random(8),
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(6),
        ]);

        $signature1 = DocumentSignature::factory()->create([
            'tenant_id' => $tenant->id,
            'signable_type' => Contract::class,
            'signable_id' => $contract1->id,
            'signer_name' => $contract1->client->contact_name ?? 'Assinante Teste 1',
            'signer_email' => $contract1->client->email,
            'status' => 'pending',
            'expires_at' => now()->addDays(30),
            'token' => Str::random(32),
        ]);

        $this->command->line("✓ Contrato {$contract1->contract_number} criado (Assinatura PENDENTE)");
        $this->command->line("  Token: {$signature1->token}");
        $this->command->line("  Link: ".route('signature.sign', ['token' => $signature1->token]));

        // 2. Contrato com assinatura ASSINADA
        $contract2 = Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $clients->get(1)->id,
            'asset_id' => $assets->get(1)->id,
            'contract_number' => 'CTR-SIG-'.Str::random(8),
            'start_date' => now()->subMonths(3)->startOfMonth(),
            'end_date' => now()->addMonths(3),
        ]);

        $signature2 = DocumentSignature::factory()->signed()->create([
            'tenant_id' => $tenant->id,
            'signable_type' => Contract::class,
            'signable_id' => $contract2->id,
            'signer_name' => $contract2->client->contact_name ?? 'Assinante Teste 2',
            'signer_email' => $contract2->client->email,
            'status' => 'signed',
            'signed_at' => now()->subDays(5),
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'geolocation' => json_encode([
                'lat' => -23.5505,
                'lng' => -46.6333,
                'accuracy' => 50,
            ]),
            'document_hash' => hash('sha256', 'test-document-content'),
        ]);

        $this->command->line("✓ Contrato {$contract2->contract_number} criado (Assinatura ASSINADA)");
        $this->command->line("  Assinado em: ".$signature2->signed_at->format('d/m/Y H:i'));
        $this->command->line("  IP: {$signature2->ip_address}");

        // 3. Contrato com múltiplas assinaturas (2 assinadas, 1 pendente)
        $contract3 = Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $clients->get(0)->id,
            'asset_id' => $assets->get(2)->id,
            'contract_number' => 'CTR-SIG-'.Str::random(8),
            'start_date' => now()->addMonth()->startOfMonth(),
            'end_date' => now()->addMonths(12),
        ]);

        // Assinante 1 - Já assinado
        $signature3a = DocumentSignature::factory()->signed()->create([
            'tenant_id' => $tenant->id,
            'signable_type' => Contract::class,
            'signable_id' => $contract3->id,
            'signer_name' => 'Gerente Comercial',
            'signer_email' => fake()->email(),
            'status' => 'signed',
            'signed_at' => now()->subDays(2),
        ]);

        // Assinante 2 - Já assinado
        $signature3b = DocumentSignature::factory()->signed()->create([
            'tenant_id' => $tenant->id,
            'signable_type' => Contract::class,
            'signable_id' => $contract3->id,
            'signer_name' => 'Diretor Financeiro',
            'signer_email' => fake()->email(),
            'status' => 'signed',
            'signed_at' => now()->subDays(1),
        ]);

        // Assinante 3 - Pendente
        $signature3c = DocumentSignature::factory()->create([
            'tenant_id' => $tenant->id,
            'signable_type' => Contract::class,
            'signable_id' => $contract3->id,
            'signer_name' => 'Procurador',
            'signer_email' => fake()->email(),
            'status' => 'pending',
            'expires_at' => now()->addDays(15),
            'token' => Str::random(32),
        ]);

        $this->command->line("✓ Contrato {$contract3->contract_number} criado (3 Assinantes: 2 ASSINADAS, 1 PENDENTE)");
        $this->command->line("  Link para 3º assinante: ".route('signature.sign', ['token' => $signature3c->token]));

        $this->command->info('');
        $this->command->info('✓ Seeder completado! Dados de teste criados com sucesso.');
        $this->command->info('');
        $this->command->table(
            ['Contrato', 'Cliente', 'Status Assinatura', 'Ativo'],
            [
                [$contract1->contract_number, $contract1->client->name, 'Pendente', $contract1->asset->name],
                [$contract2->contract_number, $contract2->client->name, 'Assinado', $contract2->asset->name],
                [$contract3->contract_number, $contract3->client->name, '2 Assinados + 1 Pendente', $contract3->asset->name],
            ]
        );
    }
}

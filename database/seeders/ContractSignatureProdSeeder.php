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
 * Seeder SEGURO para PROD — popula apenas dados de teste do módulo de Assinatura
 *
 * ⚠️ IMPORTANTE: Este seeder é seguro pois:
 * - Usa tenants EXISTENTES (não cria nem deleta)
 * - Usa clientes EXISTENTES (não cria nem altera)
 * - Apenas ADICIONA contratos de teste
 * - Pode ser revertido manualmente se necessário
 *
 * Uso: php artisan db:seed --class=ContractSignatureProdSeeder
 *
 * Ou via deploy com interatividade:
 * echo "yes" | ssh -i key user@host "php artisan db:seed --class=ContractSignatureProdSeeder"
 */
class ContractSignatureProdSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->line('');
        $this->command->info('╔════════════════════════════════════════════════════════════╗');
        $this->command->info('║ SEEDER DE TESTE — Módulo de Assinatura Eletrônica (PROD)   ║');
        $this->command->info('╚════════════════════════════════════════════════════════════╝');
        $this->command->line('');

        // Confirmar antes de continuar
        if (!$this->command->confirm('Este seeder adicionará contratos de TESTE ao seu banco PROD. Continuar?')) {
            $this->command->warn('Operação cancelada.');
            return;
        }

        // Encontrar tenant (usa o primeiro tenant ativo)
        $tenant = Tenant::whereNotNull('id')->first();

        if (!$tenant) {
            $this->command->error('❌ Nenhum tenant encontrado. Impossível continuar.');
            return;
        }

        $this->command->line("Usando tenant: {$tenant->name} ({$tenant->id})");

        // Usar clientes EXISTENTES
        $clients = Client::where('tenant_id', $tenant->id)
            ->limit(2)
            ->get();

        if ($clients->isEmpty()) {
            $this->command->error('❌ Nenhum cliente encontrado para este tenant.');
            return;
        }

        // Usar ativos EXISTENTES
        $assets = Asset::where('tenant_id', $tenant->id)
            ->limit(3)
            ->get();

        if ($assets->isEmpty()) {
            $this->command->error('❌ Nenhum ativo encontrado para este tenant.');
            return;
        }

        $this->command->line("✓ {$clients->count()} clientes encontrados");
        $this->command->line("✓ {$assets->count()} ativos encontrados");
        $this->command->line('');

        $this->command->info('Criando contratos de teste...');
        $this->command->line('');

        // 1. Contrato com assinatura PENDENTE
        try {
            $contract1 = Contract::create([
                'tenant_id' => $tenant->id,
                'client_id' => $clients->first()->id,
                'asset_id' => $assets->first()->id,
                'contract_number' => 'CTR-TEST-PROD-'.Str::random(8),
                'status' => 'Ativo',
                'start_date' => now()->startOfMonth(),
                'end_date' => now()->addMonths(6),
                'price' => fake()->randomFloat(2, 5000, 25000),
                'payment_method' => fake()->randomElement(['PIX', 'Boleto', 'Transferência']),
                'is_active' => true,
            ]);

            $sig1 = DocumentSignature::create([
                'tenant_id' => $tenant->id,
                'signable_type' => Contract::class,
                'signable_id' => $contract1->id,
                'signer_name' => 'Signatário Teste PROD',
                'signer_email' => 'teste.prod@oravel.com.br',
                'status' => 'pending',
                'expires_at' => now()->addDays(30),
                'token' => Str::random(32),
            ]);

            $this->command->line("✓ Contrato 1: {$contract1->contract_number} (PENDENTE)");
            $this->command->line("  Token: {$sig1->token}");
            $this->command->line("  Link: ".route('signature.sign', ['token' => $sig1->token]));
            $this->command->line('');
        } catch (\Exception $e) {
            $this->command->error("❌ Erro ao criar contrato 1: ".$e->getMessage());
        }

        // 2. Contrato com assinatura ASSINADA
        try {
            $contract2 = Contract::create([
                'tenant_id' => $tenant->id,
                'client_id' => $clients->last()->id,
                'asset_id' => $assets->get(1)->id ?? $assets->last()->id,
                'contract_number' => 'CTR-TEST-PROD-'.Str::random(8),
                'status' => 'Ativo',
                'start_date' => now()->subMonths(2)->startOfMonth(),
                'end_date' => now()->addMonths(4),
                'price' => fake()->randomFloat(2, 5000, 25000),
                'payment_method' => fake()->randomElement(['PIX', 'Boleto', 'Transferência']),
                'is_active' => true,
            ]);

            $sig2 = DocumentSignature::create([
                'tenant_id' => $tenant->id,
                'signable_type' => Contract::class,
                'signable_id' => $contract2->id,
                'signer_name' => 'Signatário Teste PROD',
                'signer_email' => 'teste.prod@oravel.com.br',
                'status' => 'signed',
                'signed_at' => now()->subDays(7),
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Test Agent PROD',
                'geolocation' => json_encode(['lat' => -23.5505, 'lng' => -46.6333]),
                'document_hash' => hash('sha256', 'test-prod-document'),
            ]);

            $this->command->line("✓ Contrato 2: {$contract2->contract_number} (ASSINADO)");
            $this->command->line("  Assinado em: ".$sig2->signed_at->format('d/m/Y H:i'));
            $this->command->line('');
        } catch (\Exception $e) {
            $this->command->error("❌ Erro ao criar contrato 2: ".$e->getMessage());
        }

        $this->command->info('✓ Seeder completado!');
        $this->command->info('');
        $this->command->warn('⚠️  ATENÇÃO: Contratos de teste foram criados. Para removê-los:');
        $this->command->line('   DELETE FROM contracts WHERE contract_number LIKE "CTR-TEST-PROD-%";');
        $this->command->line('   DELETE FROM document_signatures WHERE signable_type = "App\\Models\\Contract" AND signable_id IN (SELECT id FROM contracts WHERE contract_number LIKE "CTR-TEST-PROD-%");');
        $this->command->line('');
    }
}

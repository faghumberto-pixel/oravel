<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestAsaasWebhook extends Command
{
    protected $signature = 'asaas:test-webhook {--payment-id= : ID de um AccountReceivable específico para simular}';

    protected $description = 'Testa o webhook do Asaas simulando um pagamento confirmado';

    public function handle()
    {
        $token = config('services.asaas.webhook_token');
        $webhookUrl = route('asaas.webhook');

        $this->info('🔍 Status do Webhook Asaas:');
        $this->line('');
        $this->info("URL: {$webhookUrl}");
        $this->info("Token: " . ($token ? '✓ Configurado' : '✗ NÃO configurado'));
        $this->line('');

        if (!$token) {
            $this->error('❌ Token Asaas não configurado em config/services.php');
            return 1;
        }

        // Buscar um AccountReceivable recente
        $receivable = \App\Models\AccountReceivable::latest()->first();

        if (!$receivable) {
            $this->error('❌ Nenhum AccountReceivable encontrado para teste');
            return 1;
        }

        $this->info("📝 Simulando pagamento para:");
        $this->line("   ID: {$receivable->id}");
        $this->line("   Cliente: {$receivable->client?->name}");
        $this->line("   Valor: R$ " . number_format($receivable->amount, 2, ',', '.'));
        $this->line("   Status Atual: {$receivable->status}");
        $this->line('');

        // Simular payload do Asaas
        $payload = [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => $receivable->asaas_payment_id ?? 'pay_test_' . uniqid(),
                'customer' => null,
                'paymentDate' => now()->toDateString(),
            ],
        ];

        $this->info('📤 Enviando webhook simulado...');

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'asaas-access-token' => $token,
            'Content-Type' => 'application/json',
        ])->post($webhookUrl, $payload);

        $this->line("Status: {$response->status()}");
        $this->line("Response: {$response->body()}");

        if ($response->ok()) {
            $this->info('✅ Webhook recebido com sucesso!');

            // Atualizar AccountReceivable se necessário
            $receivable->refresh();
            $this->line("Status após webhook: {$receivable->status}");

            return 0;
        } else {
            $this->error('❌ Falha ao chamar webhook');
            return 1;
        }
    }
}

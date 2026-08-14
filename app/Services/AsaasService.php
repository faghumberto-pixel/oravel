<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasService
{
    private ?string $apiKey;

    private string $baseUrl;

    public function __construct()
    {
        // ?string, não string: sem ASAAS_API_KEY configurada,
        // config('services.asaas.api_key') é null -- bug real encontrado
        // 2026-08-13, o construtor quebrava com TypeError em vez de
        // degradar suave (syncTenantCustomer() abaixo trata a ausência de
        // key explicitamente, mas nunca chegava lá).
        $this->apiKey = config('services.asaas.api_key');
        $this->baseUrl = config('services.asaas.base_url');
    }

    /**
     * Cria (ou reaproveita, se já existir) o customer da Asaas
     * correspondente a este Tenant e grava asaas_customer_id/asaas_status
     * -- chamado a partir de CreateTenant::afterCreate() (painel Central).
     * Nunca lança exception pro chamador: sem API key, sem cpf_cnpj
     * preenchido, ou falha de rede/API, só loga e marca asaas_status como
     * 'error' -- criar o Tenant não pode falhar por causa da Asaas estar
     * fora do ar ou o operador ainda não ter preenchido o CPF/CNPJ (dá
     * pra sincronizar depois, editando o Tenant).
     */
    public function syncTenantCustomer(Tenant $tenant): void
    {
        if (blank($this->apiKey)) {
            Log::warning('AsaasService: API key não configurada, tenant não sincronizado.', ['tenant_id' => $tenant->id]);
            $tenant->update(['asaas_status' => 'error']);

            return;
        }

        if (blank($tenant->cpf_cnpj)) {
            Log::info('AsaasService: tenant sem CPF/CNPJ, sincronização adiada.', ['tenant_id' => $tenant->id]);
            $tenant->update(['asaas_status' => 'pending']);

            return;
        }

        try {
            $customer = $this->createCustomer([
                'name' => $tenant->name,
                'cpfCnpj' => preg_replace('/\D/', '', $tenant->cpf_cnpj),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AsaasService: falha ao criar customer.', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
            $tenant->update(['asaas_status' => 'error']);

            return;
        }

        $tenant->update([
            'asaas_customer_id' => $customer['id'] ?? null,
            'asaas_status' => 'synced',
            'asaas_synced_at' => now(),
        ]);

        $this->syncTenantSubscription($tenant);
    }

    /**
     * Cria a assinatura recorrente na Asaas pro tenant já sincronizado
     * como customer -- sem isso, o cliente ficava cadastrado na Asaas mas
     * sem nenhuma cobrança de fato configurada (o operador tinha que criar
     * a assinatura manualmente lá). billingType fica UNDEFINED de propósito
     * (pedido do usuário 2026-08-13): a Asaas gera um link de pagamento
     * onde o próprio cliente escolhe boleto/cartão/pix, em vez de travar
     * numa forma só. Mesmo tratamento de erro que syncTenantCustomer():
     * nunca lança, só loga e marca 'error' -- não bloqueia o fluxo de
     * criação do Tenant.
     */
    public function syncTenantSubscription(Tenant $tenant): void
    {
        if (blank($tenant->asaas_customer_id)) {
            // Sem customer sincronizado ainda, não faz sentido tentar
            // criar assinatura -- syncTenantCustomer() já cobre esse caso
            // (chama esta função só depois de confirmar o customer).
            return;
        }

        if (blank($tenant->mrr_value) || (float) $tenant->mrr_value <= 0) {
            Log::info('AsaasService: tenant sem MRR definido, assinatura não criada.', ['tenant_id' => $tenant->id]);

            return;
        }

        try {
            $subscription = $this->createSubscription([
                'customer' => $tenant->asaas_customer_id,
                'billingType' => 'UNDEFINED',
                'value' => (float) $tenant->mrr_value,
                'nextDueDate' => now()->addDays(7)->toDateString(),
                'cycle' => $this->mapBillingCycle($tenant->plan?->billing_cycle),
                'description' => "Assinatura Oravel -- {$tenant->name}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('AsaasService: falha ao criar assinatura.', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
            $tenant->update(['asaas_status' => 'error']);

            return;
        }

        $tenant->update([
            'asaas_subscription_id' => $subscription['id'] ?? null,
        ]);
    }

    /**
     * URL da fatura da primeira cobrança gerada pela assinatura -- é pra
     * onde o cliente recém-cadastrado é redirecionado no fluxo de
     * autoatendimento (ver AsaasCheckoutController) pra efetivamente
     * pagar. createSubscription() não devolve esse link diretamente; a
     * cobrança é gerada de forma assíncrona pela Asaas, então precisa
     * buscar depois via GET /subscriptions/{id}/payments. Retorna null
     * (não lança) se a assinatura não existir ou a cobrança ainda não
     * tiver sido gerada -- chamador decide o que fazer nesse caso.
     */
    public function getFirstInvoiceUrl(string $subscriptionId): ?string
    {
        try {
            $response = Http::withHeaders([
                'access_token' => $this->apiKey,
            ])->get("{$this->baseUrl}/subscriptions/{$subscriptionId}/payments");

            if ($response->failed()) {
                return null;
            }

            return $response->json('data.0.invoiceUrl');
        } catch (\Throwable $e) {
            Log::warning('AsaasService: falha ao buscar fatura da assinatura.', ['subscription_id' => $subscriptionId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Plan.billing_cycle é string livre no banco (sem enum), sempre visto
     * como 'monthly' nos dados existentes -- a Asaas exige um dos valores
     * fixos em maiúsculo (WEEKLY/BIWEEKLY/MONTHLY/BIMONTHLY/QUARTERLY/
     * SEMIANNUALLY/YEARLY). Default MONTHLY pra qualquer valor não
     * reconhecido, em vez de falhar a criação da assinatura por causa de
     * uma string de ciclo inesperada.
     */
    private function mapBillingCycle(?string $cycle): string
    {
        return match (strtolower((string) $cycle)) {
            'weekly' => 'WEEKLY',
            'biweekly' => 'BIWEEKLY',
            'bimonthly' => 'BIMONTHLY',
            'quarterly' => 'QUARTERLY',
            'semiannually', 'semiannual' => 'SEMIANNUALLY',
            'yearly', 'annual', 'annually' => 'YEARLY',
            default => 'MONTHLY',
        };
    }

    public function createCustomer(array $data): array
    {
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
        ])->post("{$this->baseUrl}/customers", $data);

        if ($response->failed()) {
            throw new \Exception('ASAAS Error: '.$response->body());
        }

        return $response->json();
    }

    public function createSubscription(array $data): array
    {
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
        ])->post("{$this->baseUrl}/subscriptions", $data);

        if ($response->failed()) {
            throw new \Exception('ASAAS Error: '.$response->body());
        }

        return $response->json();
    }

    public function updateSubscription(string $subscriptionId, array $data): array
    {
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
        ])->put("{$this->baseUrl}/subscriptions/{$subscriptionId}", $data);

        if ($response->failed()) {
            throw new \Exception('ASAAS Error: '.$response->body());
        }

        return $response->json();
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
        ])->delete("{$this->baseUrl}/subscriptions/{$subscriptionId}");

        if ($response->failed()) {
            throw new \Exception('ASAAS Error: '.$response->body());
        }

        return $response->json();
    }

    public function getCustomer(string $customerId): array
    {
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
        ])->get("{$this->baseUrl}/customers/{$customerId}");

        if ($response->failed()) {
            throw new \Exception('ASAAS Error: '.$response->body());
        }

        return $response->json();
    }

    public function getSubscription(string $subscriptionId): array
    {
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
        ])->get("{$this->baseUrl}/subscriptions/{$subscriptionId}");

        if ($response->failed()) {
            throw new \Exception('ASAAS Error: '.$response->body());
        }

        return $response->json();
    }
}

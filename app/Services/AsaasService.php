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

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AsaasService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.asaas.api_key');
        $this->baseUrl = config('services.asaas.base_url');
    }

    public function createCustomer(array $data): array
    {
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
        ])->post("{$this->baseUrl}/customers", $data);

        if ($response->failed()) {
            throw new \Exception("ASAAS Error: " . $response->body());
        }

        return $response->json();
    }

    public function createSubscription(array $data): array
    {
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
        ])->post("{$this->baseUrl}/subscriptions", $data);

        if ($response->failed()) {
            throw new \Exception("ASAAS Error: " . $response->body());
        }

        return $response->json();
    }

    public function updateSubscription(string $subscriptionId, array $data): array
    {
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
        ])->put("{$this->baseUrl}/subscriptions/{$subscriptionId}", $data);

        if ($response->failed()) {
            throw new \Exception("ASAAS Error: " . $response->body());
        }

        return $response->json();
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
        ])->delete("{$this->baseUrl}/subscriptions/{$subscriptionId}");

        if ($response->failed()) {
            throw new \Exception("ASAAS Error: " . $response->body());
        }

        return $response->json();
    }

    public function getCustomer(string $customerId): array
    {
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
        ])->get("{$this->baseUrl}/customers/{$customerId}");

        if ($response->failed()) {
            throw new \Exception("ASAAS Error: " . $response->body());
        }

        return $response->json();
    }

    public function getSubscription(string $subscriptionId): array
    {
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
        ])->get("{$this->baseUrl}/subscriptions/{$subscriptionId}");

        if ($response->failed()) {
            throw new \Exception("ASAAS Error: " . $response->body());
        }

        return $response->json();
    }
}

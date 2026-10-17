<?php

namespace App\Services\Cobranca;

use App\Models\TenantAsaasSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP da API do Asaas usando a conta e a chave DA EMPRESA (TenantAsaasSetting). Independente do AsaasService, que
 * fala com a conta da Oravel: nada aqui usa config('services.asaas.api_key').
 */
class AsaasEmpresaClient
{
    public function __construct(private TenantAsaasSetting $config) {}

    private function http(): PendingRequest
    {
        $base = config('services.asaas_empresa.urls.'.($this->config->ambiente === 'producao' ? 'producao' : 'sandbox'));

        return Http::baseUrl($base)->withHeaders(['access_token' => (string) $this->config->api_key, 'User-Agent' => 'Oravel'])->acceptJson()->timeout(20);
    }

    /** @throws CobrancaException */
    private function resposta($resposta): array
    {
        if ($resposta->failed()) {
            $erros = collect($resposta->json('errors') ?? [])->pluck('description')->filter()->implode(' ');

            throw new CobrancaException($erros ?: 'Asaas respondeu '.$resposta->status().'.', $resposta->status());
        }

        return (array) $resposta->json();
    }

    /** Confere a chave: devolve o saldo da conta (200) ou lança. */
    public function testar(): array
    {
        return $this->resposta($this->http()->get('/finance/balance'));
    }

    public function buscarCliente(string $cpfCnpj): ?array
    {
        $r = $this->resposta($this->http()->get('/customers', ['cpfCnpj' => $cpfCnpj, 'limit' => 1]));

        return $r['data'][0] ?? null;
    }

    public function criarCliente(array $dados): array
    {
        return $this->resposta($this->http()->post('/customers', $dados));
    }

    public function criarCobranca(array $dados): array
    {
        return $this->resposta($this->http()->post('/payments', $dados));
    }

    public function consultarCobranca(string $id): array
    {
        return $this->resposta($this->http()->get('/payments/'.rawurlencode($id)));
    }

    public function cancelarCobranca(string $id): bool
    {
        $r = $this->http()->delete('/payments/'.rawurlencode($id));

        return $r->successful() || $r->status() === 404;
    }
}

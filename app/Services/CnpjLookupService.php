<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Consulta dados cadastrais públicos de um CNPJ na BrasilAPI (dados abertos
 * da Receita Federal) pra pré-preencher o cadastro do cliente/contrato.
 * Só o CNPJ (dado público de pessoa jurídica) sai do sistema. Nunca lança:
 * devolve null se o CNPJ for inválido, não existir ou a API falhar.
 */
class CnpjLookupService
{
    /**
     * @return array<string, string|null>|null campos já mapeados pro Tenant
     */
    public function lookup(string $cnpj): ?array
    {
        $digits = preg_replace('/\D/', '', $cnpj);

        if (strlen($digits) !== 14) {
            return null;
        }

        try {
            $response = Http::timeout(10)->acceptJson()->get("https://brasilapi.com.br/api/cnpj/v1/{$digits}");
        } catch (\Throwable $e) {
            Log::warning('CnpjLookupService: falha de rede.', ['error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed() || blank($response->json('razao_social'))) {
            return null;
        }

        $phone = preg_replace('/\D/', '', (string) $response->json('ddd_telefone_1'));
        $cep = preg_replace('/\D/', '', (string) $response->json('cep'));

        return [
            'razao_social' => $response->json('razao_social'),
            'nome_fantasia' => $response->json('nome_fantasia') ?: null,
            'natureza_juridica' => $response->json('natureza_juridica') ?: null,
            'logradouro' => trim(($response->json('descricao_tipo_de_logradouro') ? $response->json('descricao_tipo_de_logradouro').' ' : '').$response->json('logradouro')) ?: null,
            'numero' => $response->json('numero') ?: null,
            'complemento' => $response->json('complemento') ?: null,
            'bairro' => $response->json('bairro') ?: null,
            'cidade' => $response->json('municipio') ?: null,
            'uf' => $response->json('uf') ?: null,
            'cep' => strlen($cep) === 8 ? substr($cep, 0, 5).'-'.substr($cep, 5) : ($cep ?: null),
            'telefone' => $phone ?: null,
            'email_contato' => $response->json('email') ?: null,
        ];
    }
}

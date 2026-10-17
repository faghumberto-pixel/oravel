<?php

namespace App\Services\Fiscal;

use App\Models\TenantFiscalSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Focus NFe (https://doc.focusnfe.com.br). O token principal da Oravel (revenda) cadastra cada empresa em /v2/empresas; a empresa
 * emite com o token próprio do ambiente. Autenticação: HTTP Basic com o token no usuário e senha vazia.
 * Campos e códigos de retorno conferidos contra a documentação ao ligar em produção (variam por município).
 */
class FocusNfeProvider implements FiscalProvider
{
    private const REGIME = ['mei' => 1, 'simples' => 1, 'simples_excesso' => 2, 'normal' => 3];

    private const STATUS = ['processando_autorizacao' => 'processando', 'autorizado' => 'autorizada', 'erro_autorizacao' => 'erro', 'cancelado' => 'cancelada'];

    private function base(string $ambiente): string
    {
        return rtrim((string) config('services.focusnfe.urls.'.($ambiente === 'producao' ? 'producao' : 'homologacao')), '/');
    }

    /** Conta da Oravel: cadastra e consulta empresas (sempre na URL de produção do emissor). */
    private function principal(): PendingRequest
    {
        $token = (string) config('services.focusnfe.token');

        if ($token === '') {
            throw new FiscalException('O emissor fiscal ainda não está configurado na Oravel. Fale com o suporte.');
        }

        return Http::baseUrl($this->base('producao'))->withBasicAuth($token, '')->acceptJson()->timeout(30);
    }

    private function daEmpresa(TenantFiscalSetting $c): PendingRequest
    {
        if (blank($c->token())) {
            throw new FiscalException('A empresa ainda não foi cadastrada no emissor fiscal. Envie o certificado digital na tela de Notas Fiscais.');
        }

        return Http::baseUrl($this->base($c->ambiente))->withBasicAuth((string) $c->token(), '')->acceptJson()->timeout(30);
    }

    /** @throws FiscalException */
    private function dados(Response $r): array
    {
        if ($r->failed()) {
            $msg = $r->json('mensagem') ?? collect($r->json('erros') ?? [])->pluck('mensagem')->filter()->implode(' ');

            throw new FiscalException($msg ?: 'O emissor fiscal respondeu '.$r->status().'.');
        }

        return (array) $r->json();
    }

    public function sincronizarEmpresa(TenantFiscalSetting $c, ?string $certificadoBase64 = null, ?string $senha = null): array
    {
        $corpo = array_filter([
            'nome' => $c->razao_social, 'nome_fantasia' => $c->nome_fantasia, 'cnpj' => preg_replace('/\D/', '', (string) $c->cnpj),
            'inscricao_estadual' => $c->inscricao_estadual, 'inscricao_municipal' => $c->inscricao_municipal,
            'regime_tributario' => self::REGIME[$c->regime_tributario] ?? null,
            'cep' => preg_replace('/\D/', '', (string) $c->cep), 'logradouro' => $c->logradouro, 'numero' => $c->numero, 'complemento' => $c->complemento,
            'bairro' => $c->bairro, 'municipio' => $c->municipio, 'uf' => $c->uf, 'email' => $c->email, 'telefone' => $c->telefone,
            'habilita_nfse' => true,
        ], fn ($v) => $v !== null && $v !== '');

        if ($certificadoBase64 !== null) {
            $corpo['arquivo_certificado_base64'] = $certificadoBase64;
            $corpo['senha_certificado'] = (string) $senha;
        }

        $http = $this->principal();
        $r = filled($c->provider_company_id) ? $http->put('/v2/empresas/'.$c->provider_company_id, $corpo) : $http->post('/v2/empresas', $corpo);
        $d = $this->dados($r);

        return [
            'id' => (string) ($d['id'] ?? $c->provider_company_id),
            'token_homologacao' => $d['token_homologacao'] ?? null,
            'token_producao' => $d['token_producao'] ?? null,
            'certificado_validade' => $d['certificado_valido_ate'] ?? null,
        ];
    }

    public function testar(TenantFiscalSetting $c): void
    {
        if (blank($c->provider_company_id)) {
            throw new FiscalException('A empresa ainda não foi cadastrada no emissor fiscal.');
        }

        $this->dados($this->principal()->get('/v2/empresas/'.$c->provider_company_id));

        if (blank($c->token())) {
            throw new FiscalException('Falta o token do ambiente escolhido. Envie o certificado de novo para sincronizar.');
        }
    }

    public function emitirNfse(TenantFiscalSetting $c, string $ref, array $dados): array
    {
        $r = $this->daEmpresa($c)->post('/v2/nfse?ref='.urlencode($ref), $dados);

        // 4xx com corpo de erro de validação: não é falha de rede, é nota recusada.
        if ($r->status() === 422 || $r->status() === 400) {
            return ['status' => 'erro', 'mensagem' => (string) ($r->json('mensagem') ?: 'Dados recusados pelo emissor.')];
        }

        return $this->normalizar($this->dados($r));
    }

    public function consultarNfse(TenantFiscalSetting $c, string $ref): array
    {
        return $this->normalizar($this->dados($this->daEmpresa($c)->get('/v2/nfse/'.urlencode($ref))));
    }

    public function cancelarNfse(TenantFiscalSetting $c, string $ref, string $motivo): array
    {
        return $this->normalizar($this->dados($this->daEmpresa($c)->send('DELETE', '/v2/nfse/'.urlencode($ref), ['json' => ['justificativa' => $motivo]])));
    }

    /** Converte a resposta do emissor para o formato único do sistema. */
    private function normalizar(array $d): array
    {
        $xml = $d['caminho_xml_nota_fiscal'] ?? null;

        return [
            'status' => self::STATUS[$d['status'] ?? ''] ?? 'processando',
            'numero' => $d['numero'] ?? null,
            'serie' => $d['serie'] ?? null,
            'codigo_verificacao' => $d['codigo_verificacao'] ?? null,
            'pdf_url' => $d['url'] ?? ($d['url_danfse'] ?? null),
            'xml_url' => $xml ? 'https://api.focusnfe.com.br'.$xml : null,
            'emitida_em' => $d['data_emissao'] ?? null,
            'mensagem' => collect($d['erros'] ?? [])->pluck('mensagem')->filter()->implode(' ') ?: ($d['mensagem'] ?? null),
        ];
    }
}

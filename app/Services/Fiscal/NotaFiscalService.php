<?php

namespace App\Services\Fiscal;

use App\Models\AccountReceivable;
use App\Models\NotaFiscal;
use App\Models\TenantFiscalSetting;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

/**
 * Emite, consulta e acompanha a NFS-e de uma conta a receber. Fala com o emissor só por FiscalProvider. O retorno do emissor é
 * assíncrono: a nota nasce "processando" e vira autorizada/erro pelo webhook ou por `fiscal:reconsultar`.
 */
class NotaFiscalService
{
    public function __construct(private FiscalProvider $provider) {}

    /** @throws FiscalException com mensagem pronta para a tela */
    public function emitir(AccountReceivable $conta, ?User $ator = null): NotaFiscal
    {
        $config = TenantFiscalSetting::daEmpresa($conta->tenant_id);

        if (! $config?->pronta()) {
            throw new FiscalException('As notas fiscais não estão ligadas para esta empresa. Ative em Configurações → Notas fiscais.');
        }

        if ((float) $conta->amount <= 0) {
            throw new FiscalException('A conta precisa ter valor maior que zero para emitir a nota.');
        }

        $tomador = $this->tomador($conta);

        if ($this->notaViva($conta)) {
            throw new FiscalException('Esta conta já tem uma NFS-e emitida ou em emissão.');
        }

        try {
            $nota = NotaFiscal::withoutGlobalScopes()->create([
                'tenant_id' => $conta->tenant_id, 'account_receivable_id' => $conta->id, 'client_id' => $conta->client_id, 'contract_id' => $conta->contract_id,
                'user_id' => $ator?->id, 'ref' => $this->ref($conta), 'ambiente' => $config->ambiente, 'status' => NotaFiscal::PROCESSANDO,
                'valor' => $conta->amount, 'tomador' => $tomador, 'discriminacao' => $this->discriminacao($config, $conta),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Duas emissões ao mesmo tempo: o índice parcial garante uma única nota viva por conta.
            throw new FiscalException('Esta conta já tem uma NFS-e emitida ou em emissão.');
        }

        try {
            $retorno = $this->provider->emitirNfse($config, $nota->ref, $this->dados($config, $nota));
        } catch (ConnectionException $e) {
            // Sem confirmação: a nota pode ter chegado ao emissor. Fica "processando" e é conferida pela MESMA referência (sem duplicar).
            $nota->forceFill(['erro' => 'Sem confirmação do emissor; conferindo automaticamente.'])->save();
            Log::warning('NFS-e sem confirmação do emissor', ['nota' => $nota->id]);

            return $nota;
        } catch (FiscalException $e) {
            $this->aplicar($nota, ['status' => 'erro', 'mensagem' => $e->getMessage()]);

            throw $e;
        }

        $this->aplicar($nota, $retorno);

        return $nota->refresh();
    }

    /** Pergunta ao emissor como está a nota e aplica o resultado. @throws FiscalException */
    public function reconsultar(NotaFiscal $nota): NotaFiscal
    {
        $config = TenantFiscalSetting::daEmpresa($nota->tenant_id);

        if (! $config || blank($config->token())) {
            throw new FiscalException('Empresa sem cadastro no emissor fiscal.');
        }

        $this->aplicar($nota, $this->provider->consultarNfse($config, $nota->ref));

        return $nota->refresh();
    }

    /** Emissão automática ao pagamento: nunca derruba quem marcou a conta como paga. */
    public function emitirAoReceber(AccountReceivable $conta): void
    {
        try {
            $config = TenantFiscalSetting::daEmpresa($conta->tenant_id);

            if ($config?->emissao_automatica && $config->pronta() && ! $this->notaViva($conta)) {
                $this->emitir($conta);
            }
        } catch (\Throwable $e) {
            Log::info('NFS-e automática não emitida', ['conta' => $conta->id, 'motivo' => $e->getMessage()]);
        }
    }

    public function notaViva(AccountReceivable $conta): ?NotaFiscal
    {
        return NotaFiscal::withoutGlobalScopes()->where('tenant_id', $conta->tenant_id)->where('account_receivable_id', $conta->id)
            ->whereIn('status', [NotaFiscal::RASCUNHO, NotaFiscal::PROCESSANDO, NotaFiscal::AUTORIZADA])->first();
    }

    /** Referência única e estável por tentativa: uma nova tentativa depois de erro ganha outro número. */
    private function ref(AccountReceivable $conta): string
    {
        $tentativa = NotaFiscal::withoutGlobalScopes()->where('account_receivable_id', $conta->id)->count() + 1;

        return 'orv-'.substr(str_replace('-', '', (string) $conta->tenant_id), 0, 8).'-'.str_replace('-', '', (string) $conta->id).'-'.$tentativa;
    }

    /** @return array<string, mixed> */
    private function tomador(AccountReceivable $conta): array
    {
        $cliente = $conta->client;

        if (! $cliente) {
            throw new FiscalException('A conta precisa de um cliente para emitir a nota.');
        }

        $doc = preg_replace('/\D/', '', (string) $cliente->cpf_cnpj);

        if (! in_array(strlen($doc), [11, 14], true)) {
            throw new FiscalException('O cliente precisa de CPF ou CNPJ válido no cadastro para emitir a nota.');
        }

        return array_filter([
            'documento' => $doc, 'tipo' => strlen($doc) === 14 ? 'cnpj' : 'cpf', 'nome' => $cliente->name,
            'email' => $cliente->email_financial ?? $cliente->email ?? null,
            'cep' => preg_replace('/\D/', '', (string) ($cliente->zip_code ?: $cliente->cep)), 'logradouro' => $cliente->address,
            'bairro' => $cliente->neighborhood, 'municipio' => $cliente->city, 'uf' => $cliente->state ?: $cliente->uf,
        ], fn ($v) => filled($v));
    }

    private function discriminacao(TenantFiscalSetting $config, AccountReceivable $conta): string
    {
        return trim($config->descricao_padrao.($conta->description ? ' — '.$conta->description : ''));
    }

    /** Corpo enviado ao emissor (formato da NFS-e da Focus NFe). @return array<string, mixed> */
    private function dados(TenantFiscalSetting $config, NotaFiscal $nota): array
    {
        $t = $nota->tomador;

        return [
            'data_emissao' => now()->toIso8601String(),
            'prestador' => array_filter(['cnpj' => $config->cnpj, 'inscricao_municipal' => $config->inscricao_municipal, 'codigo_municipio' => $config->codigo_municipio]),
            'tomador' => array_filter([
                $t['tipo'] => $t['documento'], 'razao_social' => $t['nome'], 'email' => $t['email'] ?? null,
                'endereco' => array_filter(['logradouro' => $t['logradouro'] ?? null, 'bairro' => $t['bairro'] ?? null, 'cep' => $t['cep'] ?? null, 'uf' => $t['uf'] ?? null]) ?: null,
            ]),
            'servico' => array_filter([
                'aliquota' => (float) $config->aliquota_iss, 'discriminacao' => $nota->discriminacao, 'iss_retido' => $config->iss_retido,
                'item_lista_servico' => $config->item_lista_servico, 'codigo_tributario_municipio' => $config->codigo_tributario_municipio,
                'valor_servicos' => (float) $nota->valor, 'codigo_municipio' => $config->codigo_municipio,
            ], fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /** Aplica o retorno normalizado do emissor à nota. */
    private function aplicar(NotaFiscal $nota, array $r): void
    {
        $status = in_array($r['status'] ?? '', [NotaFiscal::PROCESSANDO, NotaFiscal::AUTORIZADA, NotaFiscal::ERRO, NotaFiscal::CANCELADA], true) ? $r['status'] : NotaFiscal::PROCESSANDO;

        // Uma nota cancelada ou autorizada não volta a "processando" por retorno atrasado do emissor.
        if (in_array($nota->status, [NotaFiscal::CANCELADA], true) || ($nota->status === NotaFiscal::AUTORIZADA && $status === NotaFiscal::PROCESSANDO)) {
            return;
        }

        $dados = ['status' => $status, 'erro' => $status === NotaFiscal::ERRO ? mb_substr((string) ($r['mensagem'] ?: 'O emissor recusou a nota.'), 0, 1000) : null];

        if ($status === NotaFiscal::AUTORIZADA) {
            $dados += [
                'numero' => $r['numero'] ?? $nota->numero, 'serie' => $r['serie'] ?? $nota->serie, 'codigo_verificacao' => $r['codigo_verificacao'] ?? $nota->codigo_verificacao,
                'pdf_url' => $r['pdf_url'] ?? $nota->pdf_url, 'xml_url' => $r['xml_url'] ?? $nota->xml_url, 'emitida_em' => $nota->emitida_em ?? ($r['emitida_em'] ?? now()),
            ];
        }

        if ($status === NotaFiscal::CANCELADA) {
            $dados['cancelada_em'] = now();
        }

        $nota->forceFill($dados)->save();
    }
}

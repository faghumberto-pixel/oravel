<?php

namespace App\Services\Cobranca;

use App\Models\AccountReceivable;
use App\Models\Client;
use App\Models\TenantAsaasSetting;

/**
 * Cobrança automática de UMA conta a receber na conta Asaas da empresa dona dela. Idempotente: conta que já tem cobrança não gera outra.
 */
class CobrancaService
{
    public function __construct(private ?AsaasEmpresaClient $client = null) {}

    public static function configDe(AccountReceivable $conta): TenantAsaasSetting
    {
        $config = TenantAsaasSetting::daEmpresa($conta->tenant_id);

        if (! $config || ! $config->pronta()) {
            throw new CobrancaException('A cobrança automática não está ligada para esta empresa. Configure em Configurações → Cobrança automática.');
        }

        return $config;
    }

    private function cliente(TenantAsaasSetting $config): AsaasEmpresaClient
    {
        return $this->client ?? new AsaasEmpresaClient($config);
    }

    /** Gera a cobrança no Asaas (boleto/Pix/cartão conforme a forma padrão) e grava links e status na conta. */
    public function gerar(AccountReceivable $conta, ?string $forma = null): AccountReceivable
    {
        if (filled($conta->asaas_payment_id)) {
            return $conta;
        }

        $config = static::configDe($conta);
        $api = $this->cliente($config);

        if ($conta->status === 'pago') {
            throw new CobrancaException('Esta conta já foi recebida.');
        }

        if ((float) $conta->amount <= 0) {
            throw new CobrancaException('O valor da conta precisa ser maior que zero.');
        }

        $cliente = $conta->client ?? throw new CobrancaException('A conta precisa de um cliente para gerar a cobrança.');
        $customerId = $this->clienteNoAsaas($api, $cliente);

        $payload = [
            'customer' => $customerId,
            'billingType' => $forma ?: $config->forma_padrao ?: 'UNDEFINED',
            'value' => round((float) $conta->amount, 2),
            // O Asaas não aceita vencimento no passado: cobrança atrasada nasce para hoje.
            'dueDate' => ($conta->due_date && $conta->due_date->isFuture() ? $conta->due_date : now())->toDateString(),
            'description' => mb_substr((string) ($conta->description ?: 'Fatura'), 0, 500),
            'externalReference' => (string) $conta->id,
        ];

        $multa = $conta->multa_percentual ?? $config->multa_percentual;
        if ($multa) {
            $payload['fine'] = ['value' => (float) $multa];
        }
        if ($config->juros_mensal) {
            $payload['interest'] = ['value' => (float) $config->juros_mensal];
        }

        $payment = $api->criarCobranca($payload);

        if (blank($payment['id'] ?? null)) {
            throw new CobrancaException('O Asaas não devolveu o identificador da cobrança.');
        }

        $conta->forceFill(['asaas_payment_id' => $payment['id'], 'cobranca_tentativas' => 0, 'cobranca_erro' => null]);

        return StatusPagamento::aplicar($conta, $payment + ['status' => 'PENDING']);
    }

    /** Cliente da empresa → customer na conta Asaas dela (reaproveita pelo CPF/CNPJ, cria se não existir). */
    public function clienteNoAsaas(AsaasEmpresaClient $api, Client $cliente): string
    {
        if (filled($cliente->asaas_customer_id)) {
            return $cliente->asaas_customer_id;
        }

        $documento = preg_replace('/\D/', '', (string) $cliente->cpf_cnpj);

        if (! in_array(strlen($documento), [11, 14], true)) {
            throw new CobrancaException("O cliente \"{$cliente->name}\" precisa de CPF ou CNPJ válido para gerar cobrança.");
        }

        $existente = $api->buscarCliente($documento);
        $id = $existente['id'] ?? $api->criarCliente(array_filter([
            'name' => $cliente->name,
            'cpfCnpj' => $documento,
            'email' => $cliente->email,
            'mobilePhone' => preg_replace('/\D/', '', (string) ($cliente->phone ?? '')) ?: null,
            'externalReference' => (string) $cliente->id,
            'notificationDisabled' => true, // quem avisa o cliente é a empresa (WhatsApp/e-mail dela)
        ]))['id'] ?? throw new CobrancaException('O Asaas não criou o cliente.');

        $cliente->forceFill(['asaas_customer_id' => $id])->save();

        return $id;
    }

    /** Cancela a cobrança no Asaas e libera a conta para gerar outra. */
    public function cancelar(AccountReceivable $conta): AccountReceivable
    {
        if (blank($conta->asaas_payment_id)) {
            return $conta;
        }

        if ($conta->status === 'pago') {
            throw new CobrancaException('Cobrança já paga não pode ser cancelada.');
        }

        $config = static::configDe($conta);

        if (! $this->cliente($config)->cancelarCobranca($conta->asaas_payment_id)) {
            throw new CobrancaException('Não foi possível cancelar a cobrança no Asaas.');
        }

        $conta->forceFill(['asaas_payment_id' => null, 'asaas_invoice_url' => null, 'asaas_boleto_url' => null, 'asaas_status' => 'DELETED'])->save();

        return $conta;
    }

    /** Consulta o Asaas e atualiza a conta (usado por quem não quer esperar o webhook e pelo comando de conferência). */
    public function sincronizar(AccountReceivable $conta): AccountReceivable
    {
        if (blank($conta->asaas_payment_id)) {
            return $conta;
        }

        $payment = $this->cliente(static::configDe($conta))->consultarCobranca($conta->asaas_payment_id);

        return StatusPagamento::aplicar($conta, $payment);
    }
}

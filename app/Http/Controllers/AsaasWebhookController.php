<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recebe eventos de cobrança da Asaas (gateway de pagamento) sobre a
 * assinatura SaaS que cada Tenant paga pra Oravel -- não confundir com
 * AccountPayable (fornecedores da própria Oravel) nem AccountReceivable
 * (cada tenant cobrando os PRÓPRIOS clientes dele), nenhum dos dois tem
 * relação com esse fluxo. Atualiza Tenant.asaas_payment_status, campo
 * separado de asaas_status (que é sobre sincronização de CADASTRO
 * customer/subscription, não sobre status de pagamento da cobrança).
 *
 * Autenticação: header 'asaas-access-token', comparado contra
 * config('services.asaas.webhook_token') -- mecanismo real do Asaas
 * (token estático configurado no painel deles ao cadastrar o webhook,
 * não HMAC). Sempre responde 200 rápido (mesmo em payload inválido ou
 * evento não tratado) -- sem isso a Asaas reenvia com retry agressivo e
 * pode até pausar a fila de eventos após falhas consecutivas.
 */
class AsaasWebhookController extends Controller
{
    /**
     * Eventos que representam a cobrança em dia -- a mais recente
     * confirma que o pagamento entrou.
     */
    private const PAYMENT_OK_EVENTS = ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'];

    private const PAYMENT_OVERDUE_EVENTS = ['PAYMENT_OVERDUE'];

    private const PAYMENT_CANCELLED_EVENTS = ['PAYMENT_DELETED', 'PAYMENT_REFUNDED'];

    public function handle(Request $request): JsonResponse
    {
        $expectedToken = config('services.asaas.webhook_token');

        if (blank($expectedToken) || $request->header('asaas-access-token') !== $expectedToken) {
            Log::warning('AsaasWebhookController: token de acesso inválido ou não configurado.');

            return response()->json(['status' => 'unauthorized'], 401);
        }

        try {
            $this->process($request->all());
        } catch (\Throwable $e) {
            // Nunca deixa uma falha de processamento virar 500 -- a Asaas
            // reenvia agressivamente em caso de erro HTTP, e após 15
            // falhas consecutivas pode até interromper a fila de eventos.
            Log::warning('AsaasWebhookController: erro ao processar evento.', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'received']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function process(array $payload): void
    {
        $event = $payload['event'] ?? null;
        $payment = $payload['payment'] ?? null;

        if (blank($event) || ! is_array($payment)) {
            Log::info('AsaasWebhookController: payload sem event/payment, ignorado.', ['payload' => $payload]);

            return;
        }

        $customerId = $payment['customer'] ?? null;
        $paymentId = $payment['id'] ?? null;

        if (blank($customerId)) {
            return;
        }

        $tenant = Tenant::where('asaas_customer_id', $customerId)->first();

        if (! $tenant) {
            // Cobrança de um customer que não corresponde a nenhum tenant
            // conhecido -- pode ser de outro fluxo na mesma conta Asaas,
            // não é necessariamente um erro.
            Log::info('AsaasWebhookController: nenhum tenant encontrado para o customer.', ['customer' => $customerId]);

            return;
        }

        $newStatus = match (true) {
            in_array($event, self::PAYMENT_OK_EVENTS, true) => Tenant::PAYMENT_STATUS_EM_DIA,
            in_array($event, self::PAYMENT_OVERDUE_EVENTS, true) => Tenant::PAYMENT_STATUS_ATRASADO,
            in_array($event, self::PAYMENT_CANCELLED_EVENTS, true) => Tenant::PAYMENT_STATUS_CANCELADO,
            default => null,
        };

        if ($newStatus === null) {
            // Evento reconhecido pela Asaas mas fora do escopo deste
            // fluxo (ex: PAYMENT_CREATED, eventos de nota fiscal/
            // transferência) -- não é erro, só não altera o status.
            return;
        }

        $tenant->update([
            'asaas_payment_status' => $newStatus,
            'asaas_last_payment_id' => $paymentId,
            'asaas_payment_updated_at' => now(),
        ]);
    }
}

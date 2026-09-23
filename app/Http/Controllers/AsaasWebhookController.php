<?php

namespace App\Http\Controllers;

use App\Models\AccountReceivable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recebe eventos de cobrança da Asaas (gateway de pagamento). Trata dois fluxos:
 *
 * 1. Assinatura SaaS do Tenant (Tenant.asaas_customer_id) -- atualiza
 *    Tenant.asaas_payment_status. Campo separado de asaas_status (que é sobre
 *    sincronização de CADASTRO customer/subscription, não sobre status de
 *    pagamento da cobrança).
 *
 * 2. Contas a Receber que o Tenant cobra dos seus próprios clientes
 *    (AccountReceivable.asaas_payment_id) -- atualiza status de pagamento
 *    automático via webhook.
 *
 * Não confundir com AccountPayable (fornecedores da própria Oravel).
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

    /**
     * Eventos do Checkout (POST /v3/checkouts, ver
     * AsaasService::createTenantCheckout()) -- payload tem um objeto
     * "checkout", não "payment" como os eventos de payment tratados acima.
     * CHECKOUT_PAID é quem de fato libera o acesso do Tenant (equivalente
     * a PAYMENT_CONFIRMED/PAYMENT_RECEIVED pro fluxo antigo de assinatura).
     */
    private const CHECKOUT_OK_EVENTS = ['CHECKOUT_PAID'];

    private const CHECKOUT_CANCELLED_EVENTS = ['CHECKOUT_CANCELED', 'CHECKOUT_EXPIRED'];

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

        if (blank($event)) {
            Log::info('AsaasWebhookController: payload sem event, ignorado.', ['payload' => $payload]);

            return;
        }

        // Eventos de checkout (shape diferente: "checkout", não "payment")
        // -- tratados à parte, antes de exigir "payment" abaixo.
        if (in_array($event, [...self::CHECKOUT_OK_EVENTS, ...self::CHECKOUT_CANCELLED_EVENTS], true)) {
            $checkout = $payload['checkout'] ?? null;
            if (is_array($checkout)) {
                $this->processTenantCheckout($checkout, $event);
            }

            return;
        }

        $payment = $payload['payment'] ?? null;

        if (! is_array($payment)) {
            Log::info('AsaasWebhookController: payload sem payment, ignorado.', ['payload' => $payload]);

            return;
        }

        $customerId = $payment['customer'] ?? null;
        $paymentId = $payment['id'] ?? null;

        if (blank($paymentId)) {
            return;
        }

        // Prioridade: verificar se é um pagamento de AccountReceivable
        // (cobrança que o Tenant faz dos seus clientes)
        $receivable = AccountReceivable::where('asaas_payment_id', $paymentId)->first();

        if ($receivable) {
            $this->processReceivable($receivable, $event, $payment);

            return;
        }

        // Caso contrário, trata como assinatura SaaS do Tenant
        if (blank($customerId)) {
            return;
        }

        $tenant = Tenant::where('asaas_customer_id', $customerId)->first();

        if (! $tenant) {
            Log::info('AsaasWebhookController: nenhum tenant encontrado para o customer.', ['customer' => $customerId]);

            return;
        }

        $this->processTenantSubscription($tenant, $event, $paymentId);
    }

    /**
     * Processa evento de pagamento da assinatura SaaS do Tenant.
     */
    private function processTenantSubscription(Tenant $tenant, string $event, string $paymentId): void
    {
        $newStatus = match (true) {
            in_array($event, self::PAYMENT_OK_EVENTS, true) => Tenant::PAYMENT_STATUS_EM_DIA,
            in_array($event, self::PAYMENT_OVERDUE_EVENTS, true) => Tenant::PAYMENT_STATUS_ATRASADO,
            in_array($event, self::PAYMENT_CANCELLED_EVENTS, true) => Tenant::PAYMENT_STATUS_CANCELADO,
            default => null,
        };

        if ($newStatus === null) {
            return;
        }

        $tenant->update([
            'asaas_payment_status' => $newStatus,
            'asaas_last_payment_id' => $paymentId,
            'asaas_payment_updated_at' => now(),
        ]);

        if ($newStatus === Tenant::PAYMENT_STATUS_EM_DIA) {
            User::where('tenant_id', $tenant->id)->where('is_approved', false)->update(['is_approved' => true]);
        }
    }

    /**
     * Processa evento de checkout (assinatura via cartão/Pix, ver
     * AsaasService::createTenantCheckout()). Casa o tenant pelo
     * externalReference (= tenant->id, setado na criação do checkout)
     * PRIMEIRO, não pelo asaas_customer_id -- o Checkout não referencia um
     * customer já existente (só "customerData"), então o customer_id que
     * a Asaas de fato usou pode não ser o mesmo que syncTenantCustomer()
     * já tinha gravado. Faz fallback pra asaas_checkout_id só se o
     * externalReference vier vazio (não deveria acontecer, é sempre
     * mandado na criação, mas evita ficar cego se a Asaas omitir por
     * algum motivo).
     *
     * @param  array<string, mixed>  $checkout
     */
    private function processTenantCheckout(array $checkout, string $event): void
    {
        $externalReference = $checkout['externalReference'] ?? null;
        $checkoutId = $checkout['id'] ?? null;

        $tenant = null;
        if (filled($externalReference)) {
            $tenant = Tenant::find($externalReference);
        }
        if (! $tenant && filled($checkoutId)) {
            $tenant = Tenant::where('asaas_checkout_id', $checkoutId)->first();
        }

        if (! $tenant) {
            Log::info('AsaasWebhookController: nenhum tenant encontrado para o checkout.', ['checkout_id' => $checkoutId, 'external_reference' => $externalReference]);

            return;
        }

        $newStatus = match (true) {
            in_array($event, self::CHECKOUT_OK_EVENTS, true) => Tenant::PAYMENT_STATUS_EM_DIA,
            in_array($event, self::CHECKOUT_CANCELLED_EVENTS, true) => Tenant::PAYMENT_STATUS_CANCELADO,
            default => null,
        };

        if ($newStatus === null) {
            return;
        }

        $updates = [
            'asaas_payment_status' => $newStatus,
            'asaas_payment_updated_at' => now(),
        ];

        // Backfill: só sobrescreve se ainda não tinha um customer_id
        // sincronizado (syncTenantCustomer() já pode ter gravado um antes).
        if (blank($tenant->asaas_customer_id) && filled($checkout['customer'] ?? null)) {
            $updates['asaas_customer_id'] = $checkout['customer'];
        }

        $tenant->update($updates);

        if ($newStatus === Tenant::PAYMENT_STATUS_EM_DIA) {
            User::where('tenant_id', $tenant->id)->where('is_approved', false)->update(['is_approved' => true]);
        }
    }

    /**
     * Processa evento de pagamento de AccountReceivable (cobrança do Tenant
     * aos seus clientes).
     *
     * @param  array<string, mixed>  $payment
     */
    private function processReceivable(AccountReceivable $receivable, string $event, array $payment): void
    {
        $newStatus = match (true) {
            in_array($event, self::PAYMENT_OK_EVENTS, true) => 'pago',
            in_array($event, self::PAYMENT_OVERDUE_EVENTS, true) => 'atrasado',
            in_array($event, self::PAYMENT_CANCELLED_EVENTS, true) => 'pendente',
            default => null,
        };

        if ($newStatus === null) {
            return;
        }

        // Idempotência: não reprocessa se já está no mesmo status
        if ($receivable->status === $newStatus) {
            return;
        }

        $updates = ['status' => $newStatus];

        if ($newStatus === 'pago') {
            $updates['payment_date'] = $payment['paymentDate'] ?? now();

            // Calcula multa automaticamente se o módulo está habilitado
            if ($receivable->tenant->hasModuleEnabled('contas_a_receber')) {
                $receivable->multa_percentual ??= $receivable->contract?->multa_rescisoria;
                $updates['multa_valor'] = $receivable->calculateLateFee();
            }
        } elseif ($newStatus === 'pendente') {
            // Reembolso ou cancelamento: desfaz a baixa
            $updates['payment_date'] = null;
        }

        $receivable->update($updates);
    }
}

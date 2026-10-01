<?php

namespace App\Http\Controllers;

use App\Models\AccountReceivable;
use App\Models\ImplementationCharge;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Models\User;
use App\Services\ImplementationBillingService;
use App\Services\TenantTimeline;
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

        // Cobrança única de implantação do Tenant (AsaasService::
        // chargeTenantImplementation()) -- identificada pelo id da cobrança,
        // ANTES do caso de assinatura: senão um atraso/pagamento da
        // implantação mexeria no status da mensalidade e no bloqueio de acesso.
        $implementationCharge = ImplementationCharge::withoutGlobalScopes()
            ->where('asaas_payment_id', $paymentId)
            // Parcelas somadas à mensalidade guardam o id do pagamento da ASSINATURA;
            // esses eventos seguem o fluxo da assinatura, não o da cobrança avulsa.
            ->where('included_in_subscription', false)
            ->first();

        if ($implementationCharge) {
            $this->processImplementationCharge($implementationCharge, $event, $payment);

            return;
        }

        // Caso contrário, trata como assinatura SaaS do Tenant
        $subscriptionId = $payment['subscription'] ?? null;

        if (blank($customerId) && blank($subscriptionId)) {
            return;
        }

        // Customer primeiro; fallback pela assinatura (o Checkout pode usar um
        // customer diferente do gravado em asaas_customer_id).
        $tenant = filled($customerId) ? Tenant::where('asaas_customer_id', $customerId)->first() : null;
        if (! $tenant && filled($subscriptionId)) {
            $tenant = Tenant::where('asaas_subscription_id', $subscriptionId)->first();
        }

        if (! $tenant) {
            Log::info('AsaasWebhookController: nenhum tenant encontrado para o customer.', ['customer' => $customerId]);

            return;
        }

        $this->processTenantSubscription($tenant, $event, $payment);
    }

    /** Linha do tempo: parcela da implantação paga, atrasada ou cancelada. */
    private function recordImplementationHistory(ImplementationCharge $charge, string $newStatus): void
    {
        [$type, $title, $level] = match ($newStatus) {
            ImplementationCharge::PAGO => [TenantEvent::IMPLANTACAO_PAGA, 'Implantação paga', 'success'],
            ImplementationCharge::ATRASADO => [TenantEvent::IMPLANTACAO_ATRASADA, 'Implantação em atraso', 'danger'],
            default => [TenantEvent::IMPLANTACAO_CANCELADA, 'Implantação cancelada', 'info'],
        };
        $label = $charge->installments_total > 1 ? " (parcela {$charge->installment_number}/{$charge->installments_total})" : '';

        TenantTimeline::record(
            $charge->tenant_id,
            $type,
            $title.$label,
            'R$ '.number_format((float) $charge->amount, 2, ',', '.').'.',
            ['charge_id' => $charge->id, 'payment_id' => $charge->asaas_payment_id],
            'implantacao-'.$newStatus.':'.$charge->id,
            $newStatus !== ImplementationCharge::CANCELADO,
            $level,
        );
    }

    /**
     * Processa evento de uma parcela da cobrança única de implantação. Só
     * atualiza a própria parcela: não libera acesso nem altera
     * asaas_payment_status (isso é papel da assinatura).
     *
     * @param  array<string, mixed>  $payment
     */
    private function processImplementationCharge(ImplementationCharge $charge, string $event, array $payment): void
    {
        $newStatus = match (true) {
            in_array($event, self::PAYMENT_OK_EVENTS, true) => ImplementationCharge::PAGO,
            in_array($event, self::PAYMENT_OVERDUE_EVENTS, true) => ImplementationCharge::ATRASADO,
            in_array($event, self::PAYMENT_CANCELLED_EVENTS, true) => ImplementationCharge::CANCELADO,
            default => null,
        };

        if ($newStatus === null) {
            return;
        }

        $charge->update([
            'status' => $newStatus,
            'invoice_url' => $payment['invoiceUrl'] ?? $charge->invoice_url,
            'paid_at' => $newStatus === ImplementationCharge::PAGO ? now() : null,
        ]);

        $this->recordImplementationHistory($charge, $newStatus);
    }

    /**
     * Registra na linha do tempo do cliente o pagamento/atraso/cancelamento da
     * mensalidade (CONFIRMED e RECEIVED do mesmo pagamento viram um fato só).
     *
     * @param  array<string, mixed>  $payment
     */
    private function recordSubscriptionPaymentHistory(Tenant $tenant, string $newStatus, array $payment): void
    {
        $id = $payment['id'] ?? uniqid();
        $value = isset($payment['value']) ? 'R$ '.number_format((float) $payment['value'], 2, ',', '.') : '';
        $props = ['payment_id' => $payment['id'] ?? null, 'value' => $payment['value'] ?? null, 'invoice_url' => $payment['invoiceUrl'] ?? null];

        match ($newStatus) {
            Tenant::PAYMENT_STATUS_EM_DIA => TenantTimeline::record($tenant, TenantEvent::MENSALIDADE_PAGA, 'Mensalidade paga', trim("Pagamento de {$value} confirmado pelo Asaas."), $props, 'mensalidade-paga:'.$id, true, 'success'),
            Tenant::PAYMENT_STATUS_ATRASADO => TenantTimeline::record($tenant, TenantEvent::MENSALIDADE_ATRASADA, 'Mensalidade em atraso', trim("Cobrança de {$value} vencida e não paga."), $props, 'mensalidade-atrasada:'.$id, true, 'danger'),
            default => TenantTimeline::record($tenant, TenantEvent::PAGAMENTO_CANCELADO, 'Pagamento cancelado/estornado', trim("Cobrança de {$value} cancelada ou estornada."), $props, 'pagamento-cancelado:'.$id, true, 'danger'),
        };
    }

    /**
     * Processa evento de pagamento da assinatura SaaS do Tenant.
     *
     * @param  array<string, mixed>  $payment
     */
    private function processTenantSubscription(Tenant $tenant, string $event, array $payment): void
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

        $updates = [
            'asaas_payment_status' => $newStatus,
            'asaas_last_payment_id' => $payment['id'] ?? null,
            'asaas_payment_updated_at' => now(),
        ];

        // asaas_overdue_since marca a TRANSIÇÃO pra atrasado (pro prazo de
        // tolerância em Tenant::isAccessBlockedForNonPayment() ser confiável
        // mesmo se a Asaas reenviar o MESMO evento PAYMENT_OVERDUE) -- só
        // seta se ainda não estava atrasado; qualquer outro status novo
        // (em_dia, cancelado) limpa o campo.
        if ($newStatus === Tenant::PAYMENT_STATUS_ATRASADO) {
            $updates['asaas_overdue_since'] = $tenant->asaas_payment_status === Tenant::PAYMENT_STATUS_ATRASADO
                ? $tenant->asaas_overdue_since
                : now();
            $updates['asaas_current_invoice_url'] = $payment['invoiceUrl'] ?? $tenant->asaas_current_invoice_url;
        } else {
            $updates['asaas_overdue_since'] = null;
        }

        $tenant->update($updates);

        $this->recordSubscriptionPaymentHistory($tenant, $newStatus, $payment);

        if ($newStatus === Tenant::PAYMENT_STATUS_EM_DIA) {
            $released = User::where('tenant_id', $tenant->id)->where('is_approved', false)->update(['is_approved' => true]);

            if ($released > 0) {
                TenantTimeline::record(
                    $tenant,
                    TenantEvent::ACESSO_LIBERADO,
                    'Acesso liberado',
                    $released.' usuário(s) liberado(s) após a confirmação do pagamento.',
                    dedupeKey: 'acesso-liberado:'.($payment['id'] ?? uniqid()),
                    notify: true,
                    level: 'success',
                );
            }

            // Implantação somada à mensalidade: conta a parcela paga e, na
            // última, devolve a assinatura ao valor normal.
            app(ImplementationBillingService::class)->onSubscriptionPaymentConfirmed($tenant->refresh(), $payment);
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
            // CHECKOUT_PAID = pagamento em dia, sem prazo de tolerância
            // rodando; CHECKOUT_CANCELED/EXPIRED já bloqueiam imediato
            // (ver Tenant::isAccessBlockedForNonPayment()), esse campo é
            // só pra contagem do prazo de 'atrasado', não se aplica aqui.
            'asaas_overdue_since' => null,
        ];

        // Backfill: só sobrescreve se ainda não tinha um customer_id
        // sincronizado (syncTenantCustomer() já pode ter gravado um antes).
        if (blank($tenant->asaas_customer_id) && filled($checkout['customer'] ?? null)) {
            $updates['asaas_customer_id'] = $checkout['customer'];
        }

        $tenant->update($updates);

        $checkoutId = (string) ($checkout['id'] ?? uniqid());
        if ($newStatus === Tenant::PAYMENT_STATUS_EM_DIA) {
            TenantTimeline::record($tenant, TenantEvent::CHECKOUT_PAGO, 'Checkout pago', 'Pagamento da mensalidade no cartão confirmado.', ['checkout_id' => $checkoutId], 'checkout-pago:'.$checkoutId, true, 'success');
        } else {
            TenantTimeline::record($tenant, TenantEvent::CHECKOUT_CANCELADO, 'Checkout cancelado ou expirado', 'O cliente não concluiu o pagamento no checkout.', ['checkout_id' => $checkoutId, 'event' => $event], 'checkout-cancelado:'.$checkoutId.':'.$event, true, 'danger');
        }

        if ($newStatus === Tenant::PAYMENT_STATUS_EM_DIA) {
            $released = User::where('tenant_id', $tenant->id)->where('is_approved', false)->update(['is_approved' => true]);

            if ($released > 0) {
                TenantTimeline::record($tenant, TenantEvent::ACESSO_LIBERADO, 'Acesso liberado', $released.' usuário(s) liberado(s) após o pagamento no checkout.', dedupeKey: 'acesso-liberado-checkout:'.$checkoutId, notify: true, level: 'success');
            }
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
            $receivable->multa_percentual ??= $receivable->contract?->multa_rescisoria;
            $updates['multa_valor'] = $receivable->calculateLateFee();
        } elseif ($newStatus === 'pendente') {
            // Reembolso ou cancelamento: desfaz a baixa
            $updates['payment_date'] = null;
        }

        $receivable->update($updates);
    }
}

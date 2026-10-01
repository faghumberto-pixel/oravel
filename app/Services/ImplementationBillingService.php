<?php

namespace App\Services;

use App\Models\ImplementationCharge;
use App\Models\Tenant;
use App\Models\TenantEvent;
use Illuminate\Support\Facades\Log;

/**
 * Implantação somada à mensalidade (ver Tenant::isImplementationSummed()):
 * o Checkout cria a assinatura já com mensalidade + 1ª parcela. A cada
 * pagamento confirmado da assinatura, uma parcela é marcada como paga; depois
 * da última, o valor da assinatura volta à mensalidade normal.
 */
class ImplementationBillingService
{
    /**
     * Chamado pelo webhook num pagamento confirmado/recebido de uma cobrança
     * de assinatura do tenant. Idempotente: o Asaas manda CONFIRMED e depois
     * RECEIVED pro mesmo pagamento, e pode reenviar eventos.
     *
     * @param  array<string, mixed>  $payment  payload "payment" do webhook
     */
    public function onSubscriptionPaymentConfirmed(Tenant $tenant, array $payment): void
    {
        $subscriptionId = $payment['subscription'] ?? null;
        $paymentId = $payment['id'] ?? null;

        if (blank($subscriptionId) || blank($paymentId) || ! $tenant->isImplementationSummed()) {
            return;
        }

        if (blank($tenant->asaas_subscription_id)) {
            $tenant->update(['asaas_subscription_id' => $subscriptionId]);
        }

        $rows = ImplementationCharge::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('included_in_subscription', true)
            ->orderBy('installment_number')->get();

        if ($rows->isEmpty()) {
            return;
        }

        if (! $rows->contains('asaas_payment_id', $paymentId)) {
            $next = $rows->first(fn (ImplementationCharge $r) => $r->status === ImplementationCharge::PENDENTE && blank($r->asaas_payment_id));

            if ($next) {
                $next->update(['status' => ImplementationCharge::PAGO, 'asaas_payment_id' => $paymentId, 'paid_at' => now()]);
                $rows = $rows->fresh();

                // Sem aviso no sino: a mensalidade paga (mesmo pagamento) já avisa.
                TenantTimeline::record(
                    $tenant,
                    TenantEvent::IMPLANTACAO_PAGA,
                    "Implantação paga (parcela {$next->installment_number}/{$next->installments_total}, somada à mensalidade)",
                    'R$ '.number_format((float) $next->amount, 2, ',', '.').' dentro do pagamento da mensalidade.',
                    ['charge_id' => $next->id, 'payment_id' => $paymentId],
                    'implantacao-paga:'.$next->id,
                );
            }
        }

        $allPaid = $rows->every(fn (ImplementationCharge $r) => $r->status === ImplementationCharge::PAGO);

        // Última parcela paga: assinatura volta à mensalidade normal. Se a
        // chamada à Asaas falhar, reverted_at fica vazio e o próximo evento
        // de pagamento tenta de novo.
        if ($allPaid && blank($tenant->subscription_reverted_to_base_at)) {
            $ok = app(AsaasService::class)->updateSubscriptionValue($subscriptionId, (float) $tenant->mrr_value);

            if ($ok) {
                $tenant->update(['subscription_reverted_to_base_at' => now()]);
            } else {
                Log::warning('ImplementationBillingService: assinatura não voltou ao valor base, tentará no próximo evento.', ['tenant_id' => $tenant->id]);
            }
        }
    }
}

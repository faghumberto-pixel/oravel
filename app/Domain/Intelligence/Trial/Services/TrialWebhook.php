<?php

namespace App\Domain\Intelligence\Trial\Services;

use App\Domain\Intelligence\Trial\Enums\StatusConta;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Models\IntelligenceWebhookEvento;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * Eventos do Asaas para contas do Intelligence. Idempotente: o mesmo evento (id do Asaas, ou evento+pagamento+status) só vale uma vez.
 * Chamado pelo AsaasWebhookController depois da validação do token; nunca lança.
 */
class TrialWebhook
{
    public function __construct(private AssinaturaPlano $assinaturas) {}

    /** @return bool true se o evento pertence a uma conta de teste (foi tratado ou já havia sido) */
    public function tratar(array $payload): bool
    {
        $evento = (string) ($payload['event'] ?? '');
        $pagamento = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $assinatura = is_array($payload['subscription'] ?? null) ? $payload['subscription'] : [];

        $subId = $assinatura['id'] ?? $pagamento['subscription'] ?? null;
        $customerId = $pagamento['customer'] ?? $assinatura['customer'] ?? null;

        $trial = $subId ? IntelligenceTrial::withoutGlobalScopes()->where('asaas_subscription_id', $subId)->first() : null;
        $trial ??= $customerId ? IntelligenceTrial::withoutGlobalScopes()->where('asaas_customer_id', $customerId)->first() : null;
        if (! $trial) {
            return false;
        }

        $chave = (string) ($payload['id'] ?? sha1($evento.'|'.($pagamento['id'] ?? $assinatura['id'] ?? '').'|'.($pagamento['status'] ?? $assinatura['status'] ?? '')));
        try {
            IntelligenceWebhookEvento::create(['chave' => $chave, 'evento' => $evento, 'trial_id' => $trial->id]);
        } catch (UniqueConstraintViolationException) {
            Log::info('Intelligence trial: evento do Asaas repetido, ignorado.', ['evento' => $evento, 'trial_id' => $trial->id]);

            return true;
        }

        match ($evento) {
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => $this->pago($trial),
            'PAYMENT_OVERDUE' => $this->atrasado($trial),
            'SUBSCRIPTION_DELETED', 'SUBSCRIPTION_INACTIVATED' => $this->assinaturas->marcarCancelada($trial),
            default => null, // PAYMENT_CREATED, PAYMENT_DELETED etc.: só registrados (não mudam o acesso)
        };
        Log::info('Intelligence trial: evento do Asaas.', ['evento' => $evento, 'trial_id' => $trial->id, 'status' => $trial->fresh()->statusAtual()->value]);

        return true;
    }

    private function pago(IntelligenceTrial $trial): void
    {
        $trial->forceFill(['overdue_since' => null, 'status' => StatusConta::Active])->save();
    }

    private function atrasado(IntelligenceTrial $trial): void
    {
        $trial->forceFill(['overdue_since' => $trial->overdue_since ?? now()])->save();
        $trial->forceFill(['status' => StatusConta::Overdue])->save();
    }
}

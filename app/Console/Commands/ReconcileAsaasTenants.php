<?php

namespace App\Console\Commands;

use App\Http\Controllers\AsaasWebhookController;
use App\Models\ImplementationCharge;
use App\Models\Tenant;
use App\Models\TenantEvent;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Rede de segurança do webhook do Asaas: consulta os pagamentos de cada cliente
 * (Tenant) e, para os recebidos/vencidos que o sistema ainda não registrou, reenvia
 * o evento pelo próprio AsaasWebhookController -- mesma regra, idempotente. Nasceu
 * do incidente de 01/10/2026 (webhook com token errado: Marivan pagou e o sistema
 * não soube). Agendado por cron próprio no www-data, NÃO pelo schedule:run.
 */
class ReconcileAsaasTenants extends Command
{
    protected $signature = 'asaas:reconcile-tenants {--dry-run : Só mostra o que reenviaria}';

    protected $description = 'Reenvia ao sistema pagamentos do Asaas (clientes) que o webhook não registrou';

    public function handle(): int
    {
        $key = config('services.asaas.api_key');
        $token = config('services.asaas.webhook_token');
        $base = config('services.asaas.base_url');

        if (blank($key) || blank($token)) {
            $this->warn('Sem chave do Asaas ou token do webhook: nada a fazer.');

            return self::FAILURE;
        }

        $replayed = 0;

        foreach (Tenant::withoutGlobalScopes()->whereNotNull('asaas_customer_id')->get() as $tenant) {
            $response = Http::withHeaders(['access_token' => $key])->get("$base/payments", ['customer' => $tenant->asaas_customer_id, 'limit' => 100]);

            if ($response->failed()) {
                continue;
            }

            foreach ($response->json('data') ?? [] as $payment) {
                // Vencimento remarcado de uma cobrança em atraso: o webhook não assina PAYMENT_UPDATED,
                // então a mudança só chega por aqui.
                if ($this->dueDateWasPostponed($tenant, $payment)) {
                    $this->line("{$tenant->name}: PAYMENT_UPDATED {$payment['id']}".($this->option('dry-run') ? ' (simulação)' : ''));
                    $replayed++;

                    if (! $this->option('dry-run')) {
                        $this->replay($token, 'PAYMENT_UPDATED', $payment);
                    }

                    continue;
                }

                $event = match ($payment['status'] ?? null) {
                    'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH' => 'PAYMENT_RECEIVED',
                    'OVERDUE' => 'PAYMENT_OVERDUE',
                    default => null,
                };

                if (! $event || $this->alreadyRecorded($tenant, $payment, $event)) {
                    continue;
                }

                $this->line("{$tenant->name}: {$event} {$payment['id']}".($this->option('dry-run') ? ' (simulação)' : ''));
                $replayed++;

                if (! $this->option('dry-run')) {
                    $this->replay($token, $event, $payment);
                }
            }
        }

        $this->info("Reconciliação concluída: {$replayed} evento(s) reenviado(s).");

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $payment */
    private function replay(string $token, string $event, array $payment): void
    {
        $request = Request::create('/api/webhooks/asaas', 'POST', [], [], [], [
            'HTTP_ASAAS_ACCESS_TOKEN' => $token, 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['event' => $event, 'payment' => $payment]));

        app(AsaasWebhookController::class)->handle($request);
    }

    /**
     * A cobrança que deixou o Tenant em atraso voltou a ser PENDENTE com vencimento futuro
     * e posterior ao início do atraso registrado.
     *
     * @param  array<string, mixed>  $payment
     */
    private function dueDateWasPostponed(Tenant $tenant, array $payment): bool
    {
        if ($tenant->asaas_payment_status !== Tenant::PAYMENT_STATUS_ATRASADO
            || ($payment['id'] ?? null) !== $tenant->asaas_last_payment_id
            || ($payment['status'] ?? null) !== 'PENDING'
            || blank($payment['dueDate'] ?? null)) {
            return false;
        }

        $novo = Carbon::parse($payment['dueDate'])->startOfDay();

        return ! $novo->isPast() && (! $tenant->asaas_overdue_since || $novo->gt($tenant->asaas_overdue_since));
    }

    /** O sistema já registrou esse fato? (parcela avulsa de implantação, ou mensalidade pelo histórico) */
    private function alreadyRecorded(Tenant $tenant, array $payment, string $event): bool
    {
        $charge = ImplementationCharge::withoutGlobalScopes()
            ->where('asaas_payment_id', $payment['id'])->where('included_in_subscription', false)->first();

        if ($charge) {
            return $event === 'PAYMENT_RECEIVED'
                ? $charge->status === ImplementationCharge::PAGO
                : in_array($charge->status, [ImplementationCharge::ATRASADO, ImplementationCharge::PAGO], true);
        }

        $dedupe = ($event === 'PAYMENT_RECEIVED' ? 'mensalidade-paga:' : 'mensalidade-atrasada:').$payment['id'];

        return TenantEvent::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('dedupe_key', $dedupe)->exists();
    }
}

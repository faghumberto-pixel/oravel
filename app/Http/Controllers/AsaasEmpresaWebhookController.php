<?php

namespace App\Http\Controllers;

use App\Models\AccountReceivable;
use App\Models\TenantAsaasSetting;
use App\Services\Cobranca\StatusPagamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Webhook do Asaas da EMPRESA (/api/webhooks/asaas-empresa/{tenant}): confirma/atrasa/estorna as contas a receber dela.
 * Isolado por empresa: o token é o da empresa e a conta só é procurada dentro dela. Cada evento vale uma vez.
 */
class AsaasEmpresaWebhookController extends Controller
{
    public function handle(Request $request, string $tenant): JsonResponse
    {
        $config = TenantAsaasSetting::daEmpresa($tenant);

        // Mesma resposta para empresa inexistente e token errado: não revela quais empresas existem.
        if (! $config || ! $config->webhook_token || ! hash_equals($config->webhook_token, (string) $request->header('asaas-access-token'))) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        if (! TenantAsaasSetting::moduloLiberado($config->tenant_id)) {
            return response()->json(['ok' => true, 'ignorado' => 'modulo_desligado']);
        }

        $evento = (string) $request->input('event');
        $payment = (array) $request->input('payment', []);
        $eventoId = (string) ($request->input('id') ?: ($payment['id'] ?? '').':'.$evento.':'.($payment['status'] ?? ''));

        if ($eventoId === '' || blank($payment['id'] ?? null)) {
            return response()->json(['ok' => true, 'ignorado' => 'sem_cobranca']);
        }

        DB::transaction(function () use ($config, $evento, $eventoId, $payment) {
            $novo = DB::table('tenant_asaas_events')->insertOrIgnore([
                'id' => (string) Str::uuid(), 'tenant_id' => $config->tenant_id, 'event_id' => $eventoId,
                'event' => $evento, 'created_at' => now(), 'updated_at' => now(),
            ]);

            if (! $novo) {
                return; // já tratado
            }

            $conta = AccountReceivable::withoutGlobalScopes()->where('tenant_id', $config->tenant_id)->where('asaas_payment_id', $payment['id'])->lockForUpdate()->first();

            if ($conta) {
                StatusPagamento::aplicar($conta, $payment, $evento);
            }
        });

        return response()->json(['ok' => true]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\NotaFiscal;
use App\Models\TenantFiscalSetting;
use App\Services\Fiscal\FiscalException;
use App\Services\Fiscal\NotaFiscalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gatilho do emissor fiscal por empresa (/api/webhooks/fiscal/{tenant}). O aviso só diz "a nota {ref} mudou": o status real é
 * sempre consultado no emissor, nunca confiado ao corpo do aviso. Isolado por empresa e idempotente por (ref, status).
 */
class FiscalWebhookController extends Controller
{
    public function handle(Request $request, string $tenant, NotaFiscalService $service): JsonResponse
    {
        $config = TenantFiscalSetting::daEmpresa($tenant);
        $token = trim(preg_replace('/^Bearer\s+/i', '', (string) ($request->header('Authorization') ?: $request->query('token', ''))));

        // Mesma resposta para empresa inexistente e token errado.
        if (! $config || ! $config->webhook_token || ! hash_equals($config->webhook_token, $token)) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        if (! TenantFiscalSetting::moduloLiberado($config->tenant_id)) {
            return response()->json(['ok' => true, 'ignorado' => 'modulo_desligado']);
        }

        $ref = (string) $request->input('ref', '');
        $nota = $ref !== '' ? NotaFiscal::withoutGlobalScopes()->where('tenant_id', $config->tenant_id)->where('ref', $ref)->first() : null;

        if (! $nota) {
            return response()->json(['ok' => true, 'ignorado' => 'nota_desconhecida']);
        }

        $eventoId = $ref.':'.(string) $request->input('status', '');

        $novo = DB::table('tenant_fiscal_events')->insertOrIgnore([
            'id' => (string) Str::uuid(), 'tenant_id' => $config->tenant_id, 'event_id' => $eventoId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        if (! $novo) {
            return response()->json(['ok' => true, 'ignorado' => 'repetido']);
        }

        try {
            $service->reconsultar($nota);
        } catch (FiscalException|\Throwable $e) {
            // Não conseguiu conferir agora: libera o aviso para o emissor reenviar e a rotina de conferência também cobre.
            DB::table('tenant_fiscal_events')->where('tenant_id', $config->tenant_id)->where('event_id', $eventoId)->delete();

            return response()->json(['ok' => false], 503);
        }

        return response()->json(['ok' => true]);
    }
}

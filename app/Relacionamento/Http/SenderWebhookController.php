<?php

namespace App\Relacionamento\Http;

use App\Http\Controllers\Controller;
use App\Relacionamento\Services\RegistraInteracao;
use App\Relacionamento\Services\SenderAssinante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/relacionamento/sender/{topico} -- webhooks do Sender (e-mail marketing: Gazeta, formulario do PDF).
 * O corpo NAO traz o nome do evento, entao cada topico do Sender aponta para um caminho proprio. Hoje so
 * `subscribers-new` (unico formato conferido com entrega real); unsubscribed/bounces entram quando houver amostra.
 *
 * Seguranca: assinatura HMAC-SHA256 (hex) do CORPO CRU no cabecalho x-sender-hmac-sha256, com o signing secret do
 * Sender em RELACIONAMENTO_SENDER_SECRET. Sem segredo configurado ou assinatura invalida = 401 (falha fechado).
 * Depois de validado, nunca devolve erro por conteudo inesperado: o Sender reenvia qualquer resposta nao-2xx.
 */
class SenderWebhookController extends Controller
{
    public const TOPICOS = ['subscribers-new'];

    public function store(Request $request, string $topico, SenderAssinante $conversor, RegistraInteracao $registra): JsonResponse
    {
        $raw = $request->getContent();

        if (! $this->assinaturaValida($raw, (string) $request->header('x-sender-hmac-sha256'))) {
            Log::warning('relacionamento.sender: assinatura invalida ou ausente', ['topico' => $topico]);

            return response()->json(['ok' => false], 401);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response()->json(['ok' => true, 'ignorado' => 'corpo invalido']);
        }

        $interacoes = $conversor->interacoes($payload);
        if ($interacoes === []) {
            Log::info('relacionamento.sender: assinante sem tag reconhecida, ignorado', [
                'tags' => collect($payload['subscriber_tags'] ?? [])->pluck('title')->all(),
            ]);

            return response()->json(['ok' => true, 'ignorado' => 'sem tag reconhecida']);
        }

        foreach ($interacoes as $dados) {
            $registra->registrar($dados);
        }

        return response()->json(['ok' => true, 'registradas' => count($interacoes)]);
    }

    private function assinaturaValida(string $raw, string $enviada): bool
    {
        $segredo = (string) config('services.relacionamento.sender_secret');
        $enviada = strtolower(trim($enviada));

        return $segredo !== '' && $enviada !== '' && hash_equals(hash_hmac('sha256', $raw, $segredo), $enviada);
    }
}

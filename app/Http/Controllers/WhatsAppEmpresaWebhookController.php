<?php

namespace App\Http\Controllers;

use App\Models\TenantWhatsappSetting;
use App\Models\WhatsappConversa;
use App\Models\WhatsappNumero;
use App\Models\WhatsappMensagem;
use App\Services\DestinatariosAvisos;
use App\Services\DistribuicaoWhatsApp;
use App\Services\WhatsAppEmpresaService;
use Filament\Notifications\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Recebe os eventos da API do WhatsApp (Meta) do número de UMA empresa:
 * mensagens que o cliente manda e o andamento das enviadas (enviada, entregue, lida).
 * A empresa vem na URL; a assinatura é conferida com o segredo cadastrado por ela.
 */
class WhatsAppEmpresaWebhookController extends Controller
{
    /** Conferência exigida pela Meta ao cadastrar a URL do webhook. */
    public function verify(Request $request, string $tenant): Response
    {
        $config = TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', $tenant)->first();

        if ($config && WhatsAppEmpresaService::moduloLiberado($tenant) && $request->query('hub_mode') === 'subscribe' && hash_equals($config->verify_token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request, string $tenant): JsonResponse
    {
        $config = TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', $tenant)->first();

        if (! $config || ! WhatsAppEmpresaService::moduloLiberado($tenant) || ! $this->assinaturaValida($request, (string) $config->app_secret)) {
            return response()->json(['status' => 'forbidden'], 403);
        }

        foreach ((array) $request->input('entry', []) as $entrada) {
            foreach ((array) ($entrada['changes'] ?? []) as $mudanca) {
                $valor = $mudanca['value'] ?? [];
                $nomes = collect($valor['contacts'] ?? [])->mapWithKeys(fn ($c) => [($c['wa_id'] ?? '') => $c['profile']['name'] ?? null]);

                // A mesma URL recebe os eventos de todos os números da empresa: o número vem em metadata.
                $numero = WhatsappNumero::withoutGlobalScopes()
                    ->where('tenant_id', $config->tenant_id)
                    ->where('phone_number_id', (string) ($valor['metadata']['phone_number_id'] ?? ''))
                    ->first();

                foreach ((array) ($valor['messages'] ?? []) as $mensagem) {
                    if ($numero) {
                        $this->receber($config, $numero, $mensagem, $nomes[$mensagem['from'] ?? ''] ?? null);
                    }
                }

                foreach ((array) ($valor['statuses'] ?? []) as $status) {
                    $this->atualizarStatus($config, $status);
                }
            }
        }

        return response()->json(['status' => 'received']);
    }

    private function receber(TenantWhatsappSetting $config, WhatsappNumero $numero, array $dados, ?string $nome): void
    {
        $waId = $dados['id'] ?? null;
        $telefone = $dados['from'] ?? null;

        if (blank($waId) || blank($telefone)) {
            return;
        }

        if (WhatsappMensagem::withoutGlobalScopes()->where('tenant_id', $config->tenant_id)->where('wa_id', $waId)->exists()) {
            return; // a Meta reenvia o mesmo evento se demorarmos a responder
        }

        $tipo = $dados['type'] ?? 'text';
        $corpo = match ($tipo) {
            'text' => $dados['text']['body'] ?? '',
            'button' => $dados['button']['text'] ?? '[botão]',
            'interactive' => $dados['interactive']['button_reply']['title'] ?? $dados['interactive']['list_reply']['title'] ?? '[resposta]',
            'image' => '[imagem recebida]'.(isset($dados['image']['caption']) ? ': '.$dados['image']['caption'] : ''),
            'audio' => '[áudio recebido]',
            'video' => '[vídeo recebido]',
            'document' => '[documento recebido]'.(isset($dados['document']['filename']) ? ': '.$dados['document']['filename'] : ''),
            'location' => '[localização recebida]',
            'sticker' => '[figurinha recebida]',
            default => '[mensagem do tipo '.$tipo.']',
        };

        $conversa = (new WhatsAppEmpresaService($config, $numero))->conversa($telefone, $nome);

        WhatsappMensagem::withoutGlobalScopes()->create([
            'tenant_id' => $config->tenant_id, 'conversa_id' => $conversa->id, 'direcao' => WhatsappMensagem::ENTRADA,
            'tipo' => $tipo === 'text' ? 'texto' : 'outro', 'corpo' => $corpo, 'wa_id' => $waId, 'status' => 'recebida',
        ]);

        $conversa->update(['ultima_mensagem_em' => now(), 'ultima_recebida_em' => now(), 'nao_lidas' => $conversa->nao_lidas + 1]);

        // Número da empresa: decide quem atende (continuidade, rodízio ou fila).
        DistribuicaoWhatsApp::atribuir($conversa->load(['lead', 'numero']), $config->refresh());

        $this->avisar($conversa->refresh(), $corpo, $config);
    }

    private function atualizarStatus(TenantWhatsappSetting $config, array $status): void
    {
        $mensagem = WhatsappMensagem::withoutGlobalScopes()->where('tenant_id', $config->tenant_id)->where('wa_id', $status['id'] ?? '')->first();

        if (! $mensagem) {
            return;
        }

        $ordem = ['enviando' => 0, 'enviada' => 1, 'entregue' => 2, 'lida' => 3];
        $novo = match ($status['status'] ?? '') {
            'sent' => 'enviada', 'delivered' => 'entregue', 'read' => 'lida', 'failed' => 'falhou', default => null,
        };

        if ($novo === 'falhou') {
            $mensagem->update(['status' => 'falhou', 'erro' => mb_substr((string) ($status['errors'][0]['title'] ?? $status['errors'][0]['message'] ?? 'O WhatsApp não entregou a mensagem.'), 0, 500)]);
        } elseif ($novo && ($ordem[$novo] ?? 0) > ($ordem[$mensagem->status] ?? -1)) {
            $mensagem->update(['status' => $novo]);
        }
    }

    /** Avisa quem cuida da conversa; na fila, os atendentes escolhidos (ou, sem eles, quem a empresa definiu para esse aviso). */
    private function avisar(WhatsappConversa $conversa, string $corpo, TenantWhatsappSetting $config): void
    {
        $naFila = ! $conversa->responsavel_user_id;

        if ($conversa->responsavel) {
            $destinatarios = collect([$conversa->responsavel]);
        } else {
            $atendentes = \App\Models\User::withoutGlobalScopes()->where('tenant_id', $conversa->tenant_id)->where('is_approved', true)->whereIn('id', (array) $config->atendentes)->get();
            $destinatarios = $atendentes->isNotEmpty() ? $atendentes : DestinatariosAvisos::para($conversa->tenant_id, 'whatsapp_recebido');
        }

        foreach ($destinatarios as $usuario) {
            Notification::make()
                ->title($naFila ? 'Nova conversa de WhatsApp na fila' : 'Nova mensagem de WhatsApp')
                ->body($conversa->titulo().': '.mb_substr($corpo, 0, 120))
                ->info()
                ->sendToDatabase($usuario);
        }
    }

    /** A Meta assina o corpo bruto com o segredo do app (HMAC-SHA256, cabeçalho X-Hub-Signature-256). */
    private function assinaturaValida(Request $request, string $segredo): bool
    {
        $cabecalho = (string) $request->header('X-Hub-Signature-256', '');

        if ($segredo === '' || ! str_starts_with($cabecalho, 'sha256=')) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $request->getContent(), $segredo), substr($cabecalho, 7));
    }
}

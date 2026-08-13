<?php

namespace App\Jobs;

use App\Models\WhatsAppChat;
use App\Models\WhatsAppMessage;
use App\Services\AnthropicApiClient;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processa uma mensagem recebida no WhatsApp da Oravel: monta o histórico
 * recente, chama o Claude (AnthropicApiClient, mesmo client usado pelos
 * outros serviços de IA do Oravel -- não OpenAI) e responde via
 * WhatsAppService. Despachado pelo WhatsAppWebhookController assim que o
 * payload chega, pra devolver 200 rápido sem esperar a chamada de IA.
 */
class ProcessWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Tag que o system prompt instrui a IA a incluir quando a conversa
     * precisa ser assumida por um humano -- checada em minúsculo/maiúsculo
     * indistintamente antes de decidir o handover.
     */
    private const HANDOVER_TAG = '[HANDOVER]';

    private const HISTORY_LIMIT = 10;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly string $phone,
        public readonly string $messageText,
    ) {}

    public function handle(AnthropicApiClient $anthropic, WhatsAppService $whatsapp): void
    {
        $chat = WhatsAppChat::firstOrCreate(
            ['phone_number' => $this->phone],
            ['status' => WhatsAppChat::STATUS_AI_HANDLING],
        );

        if ($chat->status === WhatsAppChat::STATUS_HUMAN_HANDLING) {
            // Um atendente humano já assumiu esta conversa -- a IA não
            // deve responder nem processar nada, só registrar a mensagem
            // pra o humano ver o histórico completo quando abrir o chat.
            WhatsAppMessage::create([
                'whatsapp_chat_id' => $chat->id,
                'role' => WhatsAppMessage::ROLE_USER,
                'content' => $this->messageText,
            ]);

            return;
        }

        WhatsAppMessage::create([
            'whatsapp_chat_id' => $chat->id,
            'role' => WhatsAppMessage::ROLE_USER,
            'content' => $this->messageText,
        ]);

        $history = $chat->messages()
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->values();

        $messages = $history
            ->filter(fn (WhatsAppMessage $message) => $message->role !== WhatsAppMessage::ROLE_SYSTEM)
            ->map(fn (WhatsAppMessage $message) => [
                'role' => $message->role === WhatsAppMessage::ROLE_ASSISTANT ? 'assistant' : 'user',
                'content' => $message->content,
            ])
            ->values()
            ->all();

        if ($messages === []) {
            // firstOrCreate + create acima garantem pelo menos a mensagem
            // que acabou de chegar, então isso só aconteceria se a query
            // de histórico falhasse -- defesa contra enviar 'messages'
            // vazio pra API (a Anthropic rejeita esse payload).
            return;
        }

        $result = $anthropic->sendConversation($this->systemPrompt(), $messages);

        if (! $result['ok']) {
            Log::warning('ProcessWhatsAppMessageJob: falha ao consultar a IA', [
                'chat_id' => $chat->id,
                'error' => $result['error'],
            ]);

            return;
        }

        $replyText = $result['text'];
        $needsHandover = str_contains($replyText, self::HANDOVER_TAG);
        $cleanReply = trim(str_ireplace(self::HANDOVER_TAG, '', $replyText));

        WhatsAppMessage::create([
            'whatsapp_chat_id' => $chat->id,
            'role' => WhatsAppMessage::ROLE_ASSISTANT,
            'content' => $cleanReply,
        ]);

        if ($needsHandover) {
            $chat->update(['status' => WhatsAppChat::STATUS_HUMAN_HANDLING]);
        }

        if ($cleanReply !== '') {
            $whatsapp->sendMessage($this->phone, $cleanReply);
        }
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
            Você é o atendente virtual da Oravel, uma empresa de tecnologia que
            desenvolve um ERP/CMMS para gestão de locadoras de equipamentos e
            operações de manutenção de frota.

            Seu tom é corporativo, direto, educado e objetivo -- sem gírias, sem
            emojis em excesso, sem respostas longas demais para um chat de
            WhatsApp. Responda em português do Brasil.

            Você pode: explicar o que a Oravel faz e seus planos em termos gerais,
            tirar dúvidas comerciais básicas, e ajudar o visitante a entender se o
            produto atende a operação dele.

            Você NÃO deve: inventar preços, prazos ou condições comerciais que não
            tenha certeza; prometer integrações ou funcionalidades específicas sem
            confirmação; ou continuar uma conversa que exija negociação, suporte
            técnico aprofundado ou dados sensíveis do cliente.

            Se o visitante pedir para falar com uma pessoa, demonstrar
            insatisfação, fizer uma pergunta que você não tem segurança para
            responder, ou a conversa exigir avaliação comercial/técnica mais
            detalhada, inclua a tag {$this->handoverTag()} em algum ponto da sua
            resposta (o sistema a remove antes de enviar a mensagem ao cliente) e
            explique de forma natural que um especialista vai continuar o
            atendimento.
            PROMPT;
    }

    private function handoverTag(): string
    {
        return self::HANDOVER_TAG;
    }
}

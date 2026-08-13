<?php

namespace App\Filament\Central\Resources\WhatsAppChatResource\Pages;

use App\Filament\Central\Resources\WhatsAppChatResource;
use App\Models\WhatsAppChat;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Tela de conversa (bolhas de mensagem + campo de resposta), não um form
 * CRUD -- mesmo espírito visual de resources/views/livewire/global-chat
 * (chat interno entre usuários), mas aqui é o atendente humano
 * respondendo o cliente do WhatsApp depois que a IA sinalizou [HANDOVER]
 * (ver ProcessWhatsAppMessageJob). Mensagem enviada por aqui entra como
 * role=assistant (é a resposta que o cliente recebe, do ponto de vista
 * do histórico que alimenta a IA se ela reassumir depois), não um role
 * novo -- não há distinção de "quem" respondeu como assistant no dado,
 * só no fato de ter sido enviada por esta tela em vez do Job.
 */
class ViewWhatsAppChat extends Page
{
    use InteractsWithRecord;

    protected static string $resource = WhatsAppChatResource::class;

    protected static string $view = 'filament.central.resources.whatsapp-chat-resource.pages.view-whatsapp-chat';

    public string $draftMessage = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->contact_name ?: $this->getRecord()->phone_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('return_to_ai')
                ->label('Devolver para a IA')
                ->icon('heroicon-o-cpu-chip')
                ->color('gray')
                ->visible(fn () => $this->getRecord()->status === WhatsAppChat::STATUS_HUMAN_HANDLING)
                ->requiresConfirmation()
                ->action(function () {
                    $this->getRecord()->update(['status' => WhatsAppChat::STATUS_AI_HANDLING]);
                    Notification::make()->title('Chat devolvido para a IA.')->success()->send();
                }),
            Actions\Action::make('close_chat')
                ->label('Encerrar Atendimento')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->getRecord()->status !== WhatsAppChat::STATUS_CLOSED)
                ->requiresConfirmation()
                ->action(function () {
                    $this->getRecord()->update(['status' => WhatsAppChat::STATUS_CLOSED]);
                    Notification::make()->title('Atendimento encerrado.')->success()->send();
                }),
        ];
    }

    public function getMessages()
    {
        return $this->getRecord()->messages()->orderBy('id')->get();
    }

    public function sendReply(WhatsAppService $whatsapp): void
    {
        $text = trim($this->draftMessage);

        if ($text === '') {
            return;
        }

        if ($this->getRecord()->status === WhatsAppChat::STATUS_CLOSED) {
            Notification::make()->title('Este atendimento está encerrado.')->warning()->send();

            return;
        }

        $result = $whatsapp->sendMessage($this->getRecord()->phone_number, $text);

        if (! $result['ok']) {
            Notification::make()
                ->title('Não foi possível enviar a mensagem')
                ->body($result['error'])
                ->danger()
                ->send();

            return;
        }

        WhatsAppMessage::create([
            'whatsapp_chat_id' => $this->getRecord()->id,
            'role' => WhatsAppMessage::ROLE_ASSISTANT,
            'content' => $text,
        ]);

        $this->draftMessage = '';
        $this->getRecord()->touch();
    }
}

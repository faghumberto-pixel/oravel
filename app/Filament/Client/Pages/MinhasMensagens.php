<?php

namespace App\Filament\Client\Pages;

use App\Models\Client;
use App\Models\ClientMessage;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;

/**
 * Timeline de mensagens com o Tenant, append-only. Query filtrada
 * manualmente por tenant_id+client_id (mesmo princípio de toda a Fase
 * 1/2 -- não confia no global scope de BelongsToTenant, que não enxerga
 * o guard 'client').
 */
class MinhasMensagens extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Mensagens';

    protected static string $view = 'filament.client.pages.minhas-mensagens';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
        $this->markReceivedAsRead();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('area')
                    ->label('Assunto')
                    ->options(ClientMessage::areaLabels())
                    ->required(),
                Forms\Components\Textarea::make('body')
                    ->label('Mensagem')
                    ->rows(3),
                SpatieMediaLibraryFileUpload::make('anexos')
                    ->label('Anexo (imagem ou documento)')
                    ->collection('anexos')
                    ->openable()
                    ->downloadable(),
            ])
            ->statePath('data');
    }

    public function getMessagesProperty()
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        return ClientMessage::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id)
            ->with('media')
            ->orderBy('created_at')
            ->get();
    }

    public function send(): void
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        $state = $this->form->getState();

        if (blank($state['body']) && empty($state['anexos'])) {
            Notification::make()
                ->title('Escreva algo ou anexe um arquivo')
                ->warning()
                ->send();

            return;
        }

        $message = ClientMessage::create([
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->id,
            'area' => $state['area'],
            'sender_type' => ClientMessage::SENDER_CLIENT,
            'sender_id' => $client->id,
            'body' => $state['body'] ?? null,
        ]);

        foreach ($state['anexos'] ?? [] as $tempFile) {
            $message->addMediaFromDisk($tempFile, 'public')->toMediaCollection('anexos');
        }

        $this->form->fill();

        Notification::make()
            ->title('Mensagem enviada')
            ->success()
            ->send();
    }

    private function markReceivedAsRead(): void
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        ClientMessage::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id)
            ->where('sender_type', ClientMessage::SENDER_USER)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function guard(): Guard
    {
        return Auth::guard('client');
    }
}

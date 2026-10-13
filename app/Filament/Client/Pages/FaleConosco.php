<?php

namespace App\Filament\Client\Pages;

use App\Models\Client;
use App\Models\ClientFeedback;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;

/**
 * Portal do Cliente: registrar reclamação, sugestão ou elogio e acompanhar a resposta da locadora pelo protocolo.
 * A equipe responde em App\Filament\Resources\ClientFeedbackResource (menu Comercial > Voz do Cliente).
 */
class FaleConosco extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $navigationLabel = 'Fale Conosco';

    protected static ?string $title = 'Reclamação, Sugestão ou Elogio';

    protected static ?string $slug = 'fale-conosco';

    protected static string $view = 'filament.client.pages.fale-conosco';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Radio::make('type')
                    ->label('O que você quer registrar?')
                    ->options(ClientFeedback::typeLabels())
                    ->descriptions([
                        ClientFeedback::TYPE_RECLAMACAO => 'Algo deu errado ou não foi como combinado.',
                        ClientFeedback::TYPE_SUGESTAO => 'Uma ideia para melhorar o atendimento ou o serviço.',
                        ClientFeedback::TYPE_ELOGIO => 'Algo que foi bem feito e merece reconhecimento.',
                    ])
                    ->in(array_keys(ClientFeedback::typeLabels()))
                    ->required(),
                Forms\Components\TextInput::make('subject')
                    ->label('Assunto')
                    ->maxLength(191)
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('Conte o que aconteceu')
                    ->rows(5)
                    ->maxLength(5000)
                    ->required(),
                Forms\Components\FileUpload::make('anexos')
                    ->label('Anexo (foto ou documento, opcional)')
                    ->multiple()
                    ->maxFiles(5)
                    ->maxSize(8192)
                    ->acceptedFileTypes(['image/*', 'application/pdf'])
                    ->directory('fale-conosco-portal-cliente'),
            ])
            ->statePath('data');
    }

    public function send(): void
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        $state = $this->form->getState();

        $feedback = ClientFeedback::create([
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->id,
            'type' => $state['type'],
            'subject' => $state['subject'],
            'description' => $state['description'],
            'status' => ClientFeedback::STATUS_RECEBIDO,
        ]);

        foreach ($state['anexos'] ?? [] as $arquivo) {
            $feedback->addMediaFromDisk($arquivo, 'public')->toMediaCollection('anexos');
        }

        $this->form->fill();

        Notification::make()
            ->title('Registro enviado: '.$feedback->protocol)
            ->body('Guarde o protocolo para acompanhar a resposta aqui nesta tela.')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        return $table
            ->heading('Meus registros')
            ->query(
                ClientFeedback::withoutGlobalScope('tenant')
                    ->where('tenant_id', $client->tenant_id)
                    ->where('client_id', $client->id)
            )
            ->columns([
                Tables\Columns\TextColumn::make('protocol')->label('Protocolo'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ClientFeedback::typeLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        ClientFeedback::TYPE_RECLAMACAO => 'danger',
                        ClientFeedback::TYPE_ELOGIO => 'success',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('subject')->label('Assunto')->limit(40),
                Tables\Columns\TextColumn::make('created_at')->label('Registrado em')->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ClientFeedback::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        ClientFeedback::STATUS_CONCLUIDO => 'success',
                        ClientFeedback::STATUS_EM_ANALISE => 'warning',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('resposta')
                    ->label('Ver resposta')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('gray')
                    ->modalHeading(fn (ClientFeedback $record) => 'Protocolo '.$record->protocol)
                    ->modalContent(fn (ClientFeedback $record) => view('filament.client.pages.fale-conosco-resposta', ['registro' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Nenhum registro ainda');
    }

    private function guard(): Guard
    {
        return Auth::guard('client');
    }
}

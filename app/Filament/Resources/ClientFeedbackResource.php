<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientFeedbackResource\Pages;
use App\Models\ClientFeedback;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/** Reclamações, sugestões e elogios que os clientes registram no Portal do Cliente (tela Fale Conosco). */
class ClientFeedbackResource extends BaseResource
{
    protected static ?string $model = ClientFeedback::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão Comercial';

    protected static ?string $navigationLabel = 'Voz do Cliente';

    protected static ?string $modelLabel = 'Registro do cliente';

    protected static ?string $pluralModelLabel = 'Voz do Cliente';

    protected static ?int $navigationSort = 9;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('protocol')->label('Protocolo')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ClientFeedback::typeLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        ClientFeedback::TYPE_RECLAMACAO => 'danger',
                        ClientFeedback::TYPE_ELOGIO => 'success',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('client.name')->label('Cliente')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('subject')->label('Assunto')->limit(45)->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ClientFeedback::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        ClientFeedback::STATUS_CONCLUIDO => 'success',
                        ClientFeedback::STATUS_EM_ANALISE => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('Registrado em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('Tipo')->options(ClientFeedback::typeLabels()),
                Tables\Filters\SelectFilter::make('status')->label('Situação')->options(ClientFeedback::statusLabels()),
            ])
            ->actions([
                Tables\Actions\Action::make('responder')
                    ->label('Abrir e responder')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->modalHeading(fn (ClientFeedback $record) => ($record->type ? ClientFeedback::typeLabels()[$record->type] : 'Registro').' '.$record->protocol)
                    ->modalSubmitActionLabel('Salvar')
                    ->fillForm(fn (ClientFeedback $record) => ['status' => $record->status, 'response' => $record->response])
                    ->form(fn (ClientFeedback $record) => [
                        Forms\Components\Placeholder::make('quem')->label('Cliente')->content($record->client?->name ?? '—'),
                        Forms\Components\Placeholder::make('assunto')->label('Assunto')->content($record->subject),
                        Forms\Components\Placeholder::make('texto')->label('O que o cliente escreveu')
                            ->content(new HtmlString('<div style="white-space:pre-wrap">'.e($record->description).'</div>')),
                        Forms\Components\Placeholder::make('anexos')->label('Anexos')->visible($record->getMedia('anexos')->isNotEmpty())
                            ->content(new HtmlString($record->getMedia('anexos')->map(fn ($m) => '<a href="'.e($m->getUrl()).'" target="_blank" rel="noopener" style="text-decoration:underline">📎 '.e($m->file_name).'</a>')->implode('<br>'))),
                        Forms\Components\Select::make('status')->label('Situação')->options(ClientFeedback::statusLabels())->required()->native(false),
                        Forms\Components\Textarea::make('response')->label('Resposta ao cliente')->rows(4)->helperText('O cliente lê esta resposta no Portal, na tela Fale Conosco.'),
                    ])
                    ->action(function (ClientFeedback $record, array $data) {
                        $record->update([
                            'status' => $data['status'],
                            'response' => filled($data['response'] ?? null) ? $data['response'] : null,
                            'responded_by' => Auth::id(),
                            'responded_at' => now(),
                        ]);

                        Notification::make()->title('Registro atualizado')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageClientFeedbacks::route('/')];
    }
}

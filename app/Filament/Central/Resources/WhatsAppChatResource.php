<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\WhatsAppChatResource\Pages;
use App\Models\WhatsAppChat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Fila de atendimento do WhatsApp da própria Oravel (número único,
 * cross-tenant -- ver WhatsAppChat/ProcessWhatsAppMessageJob). A IA
 * responde sozinha enquanto status = ai_handling; quando ela detecta
 * [HANDOVER] no fluxo (ProcessWhatsAppMessageJob), o status vira
 * human_handling e o chat some da fila de "não respondidos" da IA --
 * essa listagem é o lugar onde um atendente humano vê e assume esses
 * chats. A conversa em si (bolhas de mensagem + campo de resposta) fica
 * na página customizada ViewWhatsAppChat, não num form CRUD padrão --
 * editar campos soltos do chat não faz sentido aqui.
 */
class WhatsAppChatResource extends Resource
{
    protected static ?string $model = WhatsAppChat::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Atendimento WhatsApp';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $modelLabel = 'Chat de WhatsApp';

    protected static ?string $pluralModelLabel = 'Chats de WhatsApp';

    public static function statusLabels(): array
    {
        return [
            WhatsAppChat::STATUS_AI_HANDLING => 'IA respondendo',
            WhatsAppChat::STATUS_HUMAN_HANDLING => 'Aguardando humano',
            WhatsAppChat::STATUS_CLOSED => 'Encerrado',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('phone_number')
                ->label('Telefone')
                ->disabled(),
            Forms\Components\TextInput::make('contact_name')
                ->label('Nome do Contato'),
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options(self::statusLabels())
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('phone_number')
                    ->label('Telefone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Contato')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        WhatsAppChat::STATUS_AI_HANDLING => 'gray',
                        WhatsAppChat::STATUS_HUMAN_HANDLING => 'warning',
                        WhatsAppChat::STATUS_CLOSED => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('messages_count')
                    ->label('Mensagens')
                    ->counts('messages'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Atividade')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::statusLabels())
                    // Fila de trabalho do humano é "aguardando humano" --
                    // filtro já vem aplicado ao abrir a listagem, sem
                    // esconder as outras opções (dá pra limpar e ver tudo).
                    ->default(WhatsAppChat::STATUS_HUMAN_HANDLING),
            ])
            ->actions([
                Tables\Actions\Action::make('view_conversation')
                    ->label('Abrir Conversa')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (WhatsAppChat $record) => Pages\ViewWhatsAppChat::getUrl(['record' => $record])),
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsAppChats::route('/'),
            'edit' => Pages\EditWhatsAppChat::route('/{record}/edit'),
            'view' => Pages\ViewWhatsAppChat::route('/{record}'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\ContratoCluster;
use App\Filament\Resources\DocumentSignatureResource\Pages;
use App\Models\DocumentSignature;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;

class DocumentSignatureResource extends Resource
{
    protected static ?string $cluster = ContratoCluster::class;

    protected static ?string $model = DocumentSignature::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Assinaturas Eletrônicas';

    protected static ?string $modelLabel = 'Assinatura Eletrônica';

    protected static ?string $pluralModelLabel = 'Assinaturas Eletrônicas';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações do Documento')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('signable_type')
                            ->label('Tipo de Documento')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => class_basename($state))
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('signable_id')
                            ->label('ID do Documento')
                            ->disabled()
                            ->columnSpan(1),
                    ]),

                Forms\Components\Section::make('Dados do Signatário')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('signer_name')
                            ->label('Nome Completo')
                            ->required()
                            ->disabled()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('signer_document')
                            ->label('CPF/CNPJ')
                            ->disabled()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('signer_email')
                            ->label('E-mail')
                            ->email()
                            ->disabled()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('signer_phone')
                            ->label('Telefone')
                            ->disabled()
                            ->columnSpan(1),
                    ]),

                Forms\Components\Section::make('Status da Assinatura')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pendente',
                                'signed' => 'Assinado',
                                'expired' => 'Expirado',
                                'canceled' => 'Cancelado',
                            ])
                            ->disabled()
                            ->columnSpan(1),

                        Forms\Components\DateTimePickerComponent::make('signed_at')
                            ->label('Assinado em')
                            ->disabled()
                            ->columnSpan(1),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expira em')
                            ->disabled()
                            ->columnSpan(1),
                    ]),

                Forms\Components\Section::make('Metadados de Assinatura')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('token')
                            ->label('Token')
                            ->disabled()
                            ->copyable()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('ip_address')
                            ->label('Endereço IP')
                            ->disabled()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('user_agent')
                            ->label('User-Agent')
                            ->disabled()
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('geolocation')
                            ->label('Geolocalização (JSON)')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('document_hash')
                            ->label('Hash SHA-256 do PDF')
                            ->disabled()
                            ->copyable()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('signature_image_path')
                            ->label('Caminho da Imagem de Assinatura')
                            ->disabled()
                            ->columnSpan(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('signer_name')
                    ->label('Signatário')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('signable_type')
                    ->label('Tipo de Doc.')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'App\\Models\\Contract' => '📋 Contrato',
                        'App\\Models\\MaintenanceOrder' => '🔧 Ordem de Serviço',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'signed',
                        'danger' => 'expired',
                        'gray' => 'canceled',
                    ])
                    ->icons([
                        'heroicon-o-clock' => 'pending',
                        'heroicon-o-check-circle' => 'signed',
                        'heroicon-o-x-circle' => 'expired',
                        'heroicon-o-minus-circle' => 'canceled',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('signer_email')
                    ->label('E-mail')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('signer_phone')
                    ->label('Telefone')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('signed_at')
                    ->label('Assinado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expira em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pendente',
                        'signed' => 'Assinado',
                        'expired' => 'Expirado',
                        'canceled' => 'Cancelado',
                    ]),

                Tables\Filters\SelectFilter::make('signable_type')
                    ->label('Tipo de Documento')
                    ->options([
                        'App\\Models\\Contract' => 'Contrato',
                        'App\\Models\\MaintenanceOrder' => 'Ordem de Serviço',
                    ]),

                Tables\Filters\Filter::make('has_email')
                    ->label('Com E-mail')
                    ->query(fn (Builder $query) => $query->whereNotNull('signer_email')),

                Tables\Filters\Filter::make('has_phone')
                    ->label('Com Telefone')
                    ->query(fn (Builder $query) => $query->whereNotNull('signer_phone')),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),

                    Tables\Actions\Action::make('view_link')
                        ->label('Ver Link')
                        ->icon('heroicon-o-link')
                        ->color('info')
                        ->url(fn (DocumentSignature $record) => route('signature.sign', ['token' => $record->token]))
                        ->openUrlInNewTab()
                        ->visible(fn (DocumentSignature $record) => $record->can_sign),

                    Tables\Actions\Action::make('copy_link')
                        ->label('Copiar Link')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('info')
                        ->action(function (DocumentSignature $record) {
                            $link = route('signature.sign', ['token' => $record->token]);
                            // Copia para clipboard via JS
                            \Filament\Notifications\Notification::make()
                                ->title('Link copiado!')
                                ->body($link)
                                ->success()
                                ->send();
                        })
                        ->visible(fn (DocumentSignature $record) => $record->can_sign),

                    Tables\Actions\Action::make('send_email')
                        ->label('Enviar E-mail')
                        ->icon('heroicon-o-envelope')
                        ->color('success')
                        ->form([
                            Forms\Components\TextInput::make('email')
                                ->label('E-mail')
                                ->email()
                                ->required()
                                ->default(fn (DocumentSignature $record) => $record->signer_email),
                        ])
                        ->action(function (DocumentSignature $record, array $data) {
                            $record->notify(new \App\Notifications\SignatureLinkNotification('email'));

                            \Filament\Notifications\Notification::make()
                                ->title('E-mail enviado!')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (DocumentSignature $record) => $record->can_sign && $record->signer_email),

                    Tables\Actions\Action::make('send_whatsapp')
                        ->label('Enviar WhatsApp')
                        ->icon('heroicon-o-chat-bubble-left')
                        ->color('success')
                        ->action(function (DocumentSignature $record) {
                            $record->notify(new \App\Notifications\SignatureLinkNotification('whatsapp'));

                            \Filament\Notifications\Notification::make()
                                ->title('Mensagem enviada!')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (DocumentSignature $record) => $record->can_sign && $record->signer_phone),

                    Tables\Actions\Action::make('renew')
                        ->label('Renovar Token')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('days')
                                ->label('Prorrogar por')
                                ->options([
                                    7 => '7 dias',
                                    14 => '14 dias',
                                    30 => '30 dias',
                                    60 => '60 dias',
                                ])
                                ->required(),
                        ])
                        ->action(function (DocumentSignature $record, array $data) {
                            app(\App\Services\SignatureService::class)
                                ->renewSignatureToken($record, $data['days']);

                            \Filament\Notifications\Notification::make()
                                ->title('Token renovado!')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (DocumentSignature $record) => $record->is_pending),

                    Tables\Actions\DeleteAction::make('cancel')
                        ->label('Cancelar')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->action(function (DocumentSignature $record) {
                            app(\App\Services\SignatureService::class)
                                ->cancelSignature($record);

                            \Filament\Notifications\Notification::make()
                                ->title('Assinatura cancelada!')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (DocumentSignature $record) => $record->is_pending),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentSignatures::route('/'),
            'view' => Pages\ViewDocumentSignature::route('/{record}'),
        ];
    }
}

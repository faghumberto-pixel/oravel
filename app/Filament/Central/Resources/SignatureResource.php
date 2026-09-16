<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\SignatureResource\Pages;
use App\Models\Signature;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class SignatureResource extends Resource
{
    protected static ?string $model = Signature::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Conformidade & Compliance';

    protected static ?string $navigationLabel = 'Assinaturas SLA + LGPD';

    protected static ?string $pluralModelLabel = 'Assinaturas';

    protected static ?string $modelLabel = 'Assinatura';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informações da Assinatura')
                ->description('Dados da empresa e responsável que assinou o SLA + LGPD')
                ->schema([
                    Forms\Components\TextInput::make('company')
                        ->label('Empresa')
                        ->disabled()
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('name')
                        ->label('Responsável Legal')
                        ->disabled(),

                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->disabled()
                        ->email(),

                    Forms\Components\TextInput::make('ip_origin')
                        ->label('IP de Origem')
                        ->disabled(),

                    Forms\Components\DateTimeField::make('signed_at')
                        ->label('Data & Hora da Assinatura')
                        ->disabled()
                        ->displayFormat('d/m/Y H:i:s'),
                ]),

            Forms\Components\Section::make('Assinatura Digital')
                ->description('Hash SHA-256 da assinatura (verificação de integridade)')
                ->schema([
                    Forms\Components\Textarea::make('hash')
                        ->label('Hash SHA-256')
                        ->disabled()
                        ->columnSpan('full')
                        ->rows(3),
                ]),

            Forms\Components\Section::make('Status do Email')
                ->schema([
                    Forms\Components\Toggle::make('email_sent')
                        ->label('Email de Confirmação Enviado')
                        ->disabled(),

                    Forms\Components\DateTimeField::make('email_sent_at')
                        ->label('Data do Envio')
                        ->disabled()
                        ->displayFormat('d/m/Y H:i:s'),
                ]),

            Forms\Components\Section::make('User-Agent & Metadados')
                ->schema([
                    Forms\Components\Textarea::make('user_agent')
                        ->label('Navegador/Cliente')
                        ->disabled()
                        ->rows(2),

                    Forms\Components\Textarea::make('metadata')
                        ->label('Metadados Adicionais (JSON)')
                        ->disabled()
                        ->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Responsável')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('signed_at')
                    ->label('Assinado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return $state ? $state->format('d \\d\\e F \\d\\e Y \\à\\s H:i:s') : null;
                    }),

                BadgeColumn::make('email_sent')
                    ->label('Email')
                    ->getStateUsing(fn (Signature $record) => $record->email_sent ? '✓ Enviado' : '✗ Pendente')
                    ->color(fn (Signature $record) => $record->email_sent ? 'success' : 'warning'),

                TextColumn::make('ip_origin')
                    ->label('IP de Origem')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('hash')
                    ->label('Hash (primeiros 16 char)')
                    ->formatStateUsing(fn ($state) => substr($state, 0, 16) . '...')
                    ->copyable(
                        fn ($state) => Signature::where('hash', 'like', substr($state, 0, 16) . '%')->first()?->hash
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('signed_at')
                    ->form([
                        Forms\Components\DatePicker::make('signed_from')
                            ->label('De'),
                        Forms\Components\DatePicker::make('signed_until')
                            ->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['signed_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('signed_at', '>=', $date))
                            ->when($data['signed_until'] ?? null, fn (Builder $q, $date) => $q->whereDate('signed_at', '<=', $date));
                    }),

                Filter::make('email_sent')
                    ->query(fn (Builder $query) => $query->where('email_sent', true))
                    ->label('Apenas emails enviados'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('resend_email')
                    ->label('Reenviar Email')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Signature $record) {
                        \Mail::to($record->email)->send(
                            new \App\Mail\SignatureAcceptedMail($record)
                        );
                        \Mail::to('suporte@oravel.com.br')->send(
                            new \App\Mail\SignatureAcceptedMail($record)
                        );
                        $record->update(['email_sent' => true, 'email_sent_at' => now()]);
                    })
                    ->successNotificationTitle('Email reenviado com sucesso'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('signed_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSignatures::route('/'),
            'view' => Pages\ViewSignature::route('/{record}'),
        ];
    }
}

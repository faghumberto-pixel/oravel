<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\LandingPageLeadResource\Pages;
use App\Filament\Central\Resources\LandingPageLeadResource\RelationManagers;
use App\Models\LandingPageLead;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LandingPageLeadResource extends Resource
{
    protected static ?string $model = LandingPageLead::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Leads da Landing Page';
    protected static ?string $modelLabel = 'Lead';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(191),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(191),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->required()
                    ->maxLength(191),
                Forms\Components\TextInput::make('company')
                    ->required()
                    ->maxLength(191),
                Forms\Components\TextInput::make('segment')
                    ->required()
                    ->maxLength(191),
                Forms\Components\Select::make('product')
                    ->options(['wms' => 'Oravel WMS', 'crm' => 'Oravel CRM'])
                    ->required()
                    ->default('wms'),
                Forms\Components\Select::make('status')
                    ->options([
                        'novo' => 'Novo',
                        'contatado' => 'Contatado',
                        'interessado' => 'Interessado',
                        'perdido' => 'Perdido',
                    ])
                    ->required()
                    ->default('novo'),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('contacted_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('company')
                    ->searchable(),
                Tables\Columns\TextColumn::make('segment')
                    ->searchable(),
                Tables\Columns\TextColumn::make('product')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'novo',
                        'info' => 'contatado',
                        'success' => 'interessado',
                        'danger' => 'perdido',
                    ])
                    ->labels([
                        'novo' => 'Novo',
                        'contatado' => 'Contatado',
                        'interessado' => 'Interessado',
                        'perdido' => 'Perdido',
                    ]),
                Tables\Columns\TextColumn::make('contacted_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('sendEmail')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->label('Enviar Email')
                    ->form([
                        Forms\Components\TextInput::make('subject')
                            ->label('Assunto')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('message')
                            ->label('Mensagem')
                            ->required()
                            ->rows(6),
                    ])
                    ->action(function (LandingPageLead $record, array $data): void {
                        try {
                            \Illuminate\Support\Facades\Mail::raw($data['message'], function ($message) use ($record, $data) {
                                $message->to($record->email)->subject($data['subject']);
                            });
                            \Filament\Notifications\Notification::make()
                                ->title('Email enviado!')
                                ->body("Email enviado com sucesso para {$record->email}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Erro ao enviar email')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLandingPageLeads::route('/'),
            'create' => Pages\CreateLandingPageLead::route('/create'),
            'edit' => Pages\EditLandingPageLead::route('/{record}/edit'),
        ];
    }
}

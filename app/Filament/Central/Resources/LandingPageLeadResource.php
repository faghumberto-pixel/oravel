<?php

namespace App\Filament\Central\Resources;

use App\Models\LandingPageLead;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;

class LandingPageLeadResource extends Resource
{
    protected static ?string $model = LandingPageLead::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Leads Landing Page';
    protected static ?string $modelLabel = 'Lead';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome')->required(),
            Forms\Components\TextInput::make('email')->label('Email')->email()->required(),
            Forms\Components\TextInput::make('phone')->label('Telefone')->tel()->required(),
            Forms\Components\TextInput::make('company')->label('Empresa')->required(),
            Forms\Components\TextInput::make('segment')->label('Segmento')->required(),
            Forms\Components\Select::make('product')->options(['wms' => 'Oravel WMS', 'crm' => 'Oravel CRM'])->required(),
            Forms\Components\Select::make('status')->options(['novo' => 'Novo', 'contatado' => 'Contatado', 'interessado' => 'Interessado', 'perdido' => 'Perdido'])->required(),
            Forms\Components\Textarea::make('notes')->label('Notas'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable(),
                Tables\Columns\TextColumn::make('phone')->label('Telefone')->searchable(),
                Tables\Columns\TextColumn::make('company')->label('Empresa')->searchable(),
                Tables\Columns\TextColumn::make('segment')->label('Segmento')->searchable(),
                Tables\Columns\TextColumn::make('product')->label('Produto'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['warning' => 'novo', 'info' => 'contatado', 'success' => 'interessado', 'danger' => 'perdido'])
                    ->labels(['novo' => 'Novo', 'contatado' => 'Contatado', 'interessado' => 'Interessado', 'perdido' => 'Perdido']),
                Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([])
            ->actions([
                Action::make('sendEmail')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->label('📧 Email')
                    ->form([
                        Forms\Components\TextInput::make('subject')->label('Assunto')->required()->maxLength(255),
                        Forms\Components\Textarea::make('message')->label('Mensagem')->required()->rows(6),
                    ])
                    ->action(function (LandingPageLead $record, array $data): void {
                        try {
                            \Illuminate\Support\Facades\Mail::raw($data['message'], function ($message) use ($record, $data) {
                                $message->to($record->email)->subject($data['subject']);
                            });
                            \Filament\Notifications\Notification::make()
                                ->title('✅ Email enviado!')
                                ->body("Email enviado para {$record->email}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('❌ Erro ao enviar')
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

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Central\Resources\LandingPageLeadResource\Pages\ListLandingPageLeads::route('/'),
            'edit' => \App\Filament\Central\Resources\LandingPageLeadResource\Pages\EditLandingPageLead::route('/{record}/edit'),
        ];
    }
}

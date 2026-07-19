<?php

namespace App\Filament\Central\Resources\SalesLeadResource\RelationManagers;

use App\Models\SalesLeadAppointment;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    protected static ?string $title = 'Compromissos (Programação)';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->label('Título')
                ->required(),
            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->options(SalesLeadAppointment::typeLabels())
                ->default(SalesLeadAppointment::TYPE_DEMONSTRACAO)
                ->required(),
            Forms\Components\DateTimePicker::make('scheduled_at')
                ->label('Data/Hora')
                ->default(now())
                ->required(),
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options(SalesLeadAppointment::statusLabels())
                ->default(SalesLeadAppointment::STATUS_PENDENTE)
                ->live()
                ->required(),
            Forms\Components\DateTimePicker::make('completed_at')
                ->label('Concluído em')
                ->visible(fn (Forms\Get $get) => $get('status') === SalesLeadAppointment::STATUS_CONCLUIDO),
            Forms\Components\Select::make('assigned_user_id')
                ->label('Responsável')
                ->options(fn () => User::whereIn('email', config('oravel.super_admins', []))->pluck('name', 'id'))
                ->searchable()
                ->preload(),
            Forms\Components\Textarea::make('notes')
                ->label('Observações')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_at')->label('Data/Hora')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('Título'),
                Tables\Columns\TextColumn::make('type')->label('Tipo')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => SalesLeadAppointment::typeLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => SalesLeadAppointment::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        SalesLeadAppointment::STATUS_CONCLUIDO => 'success',
                        SalesLeadAppointment::STATUS_EM_ANDAMENTO => 'info',
                        SalesLeadAppointment::STATUS_AGUARDANDO => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('assignedUser.name')->label('Responsável')->placeholder('—'),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Agendar Compromisso'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}

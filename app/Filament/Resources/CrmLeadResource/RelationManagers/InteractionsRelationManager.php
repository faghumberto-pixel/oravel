<?php

namespace App\Filament\Resources\CrmLeadResource\RelationManagers;

use App\Models\CrmLeadInteraction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InteractionsRelationManager extends RelationManager
{
    protected static string $relationship = 'interactions';

    protected static ?string $title = 'Interações';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DateTimePicker::make('contact_date')
                ->label('Data do Contato')
                ->default(now())
                ->required(),
            Forms\Components\Select::make('channel')
                ->label('Canal')
                ->options(CrmLeadInteraction::channelLabels())
                ->required(),
            Forms\Components\Textarea::make('summary')
                ->label('Resumo do Contato')
                ->required()
                ->columnSpanFull(),
            Forms\Components\Textarea::make('next_action')
                ->label('Próxima Ação')
                ->columnSpanFull(),
            Forms\Components\DatePicker::make('next_followup_date')
                ->label('Próximo Follow-up')
                ->minDate(now()->startOfDay())
                ->helperText('Aparece na agenda e gera lembrete no sino de notificações.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('summary')
            ->columns([
                Tables\Columns\TextColumn::make('contact_date')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('channel')->label('Canal')->badge()
                    ->formatStateUsing(fn (string $state) => CrmLeadInteraction::channelLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('user.name')->label('Registrado por'),
                Tables\Columns\TextColumn::make('summary')->label('Resumo')->limit(50),
                Tables\Columns\TextColumn::make('next_followup_date')->label('Próximo Follow-up')->date('d/m/Y')->placeholder('—'),
            ])
            ->defaultSort('contact_date', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Registrar Contato')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        $data['stage_at_time'] = $this->getOwnerRecord()->stage;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}

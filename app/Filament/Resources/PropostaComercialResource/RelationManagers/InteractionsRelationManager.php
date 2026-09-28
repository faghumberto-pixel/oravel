<?php

namespace App\Filament\Resources\PropostaComercialResource\RelationManagers;

use App\Models\PropostaComercialInteraction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InteractionsRelationManager extends RelationManager
{
    protected static string $relationship = 'interactions';

    protected static ?string $title = 'Histórico de Ações';

    /**
     * PropostaComercialResource não tem página de Edit (só index/create/
     * view, ver getPages()) -- por padrão o Filament trata o relation
     * manager como somente-leitura quando a página dona é um ViewRecord,
     * o que esconderia até o botão "Registrar Contato". Aqui é uma tela de
     * log de contato (sempre editável, mesma lógica de permissão de
     * PropostaComercialInteractionPolicy), não o conteúdo da proposta em
     * si (esse sim é read-only de propósito, ver ViewPropostaComercial).
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DateTimePicker::make('contact_date')
                ->label('Data do Contato')
                ->default(now())
                ->required(),
            Forms\Components\Select::make('channel')
                ->label('Canal')
                ->options(PropostaComercialInteraction::channelLabels())
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
                    ->formatStateUsing(fn (string $state) => PropostaComercialInteraction::channelLabels()[$state] ?? $state),
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
                        $data['status_at_time'] = $this->getOwnerRecord()->status;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}

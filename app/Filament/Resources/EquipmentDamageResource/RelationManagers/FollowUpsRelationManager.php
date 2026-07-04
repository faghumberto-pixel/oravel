<?php

namespace App\Filament\Resources\EquipmentDamageResource\RelationManagers;

use App\Models\EquipmentDamage;
use App\Models\EquipmentDamageFollowUp;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FollowUpsRelationManager extends RelationManager
{
    protected static string $relationship = 'followUps';

    protected static ?string $title = 'Follow-up de Cobrança';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DateTimePicker::make('contact_date')
                ->label('Data do contato')
                ->default(now())
                ->required(),
            Forms\Components\Select::make('channel')
                ->label('Canal')
                ->options([
                    EquipmentDamageFollowUp::CHANNEL_TELEFONE => 'Telefone',
                    EquipmentDamageFollowUp::CHANNEL_EMAIL => 'E-mail',
                    EquipmentDamageFollowUp::CHANNEL_WHATSAPP => 'WhatsApp',
                    EquipmentDamageFollowUp::CHANNEL_PRESENCIAL => 'Presencial',
                ])
                ->required(),
            Forms\Components\Textarea::make('summary')
                ->label('Resumo do contato')
                ->required()
                ->columnSpanFull(),
            Forms\Components\Textarea::make('next_action')
                ->label('Próxima ação')
                ->columnSpanFull(),
            Forms\Components\DatePicker::make('next_action_date')
                ->label('Data da próxima ação'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('summary')
            ->columns([
                Tables\Columns\TextColumn::make('contact_date')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('channel')->label('Canal')->badge(),
                Tables\Columns\TextColumn::make('user.name')->label('Responsável'),
                Tables\Columns\TextColumn::make('summary')->label('Resumo')->limit(50),
                Tables\Columns\TextColumn::make('next_action_date')->label('Próxima ação em')->date('d/m/Y'),
            ])
            ->defaultSort('contact_date', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Registrar Contato')
                    ->visible(fn () => $this->getOwnerRecord()->status === EquipmentDamage::STATUS_EM_COBRANCA)
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }
}

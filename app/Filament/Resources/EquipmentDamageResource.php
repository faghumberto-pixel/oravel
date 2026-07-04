<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentDamageResource\Pages;
use App\Models\EquipmentDamage;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class EquipmentDamageResource extends BaseResource
{
    protected static ?string $model = EquipmentDamage::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'GESTÃO DE MANUTENÇÃO';

    protected static ?string $modelLabel = 'Avaria de Equipamento';

    protected static ?string $pluralModelLabel = 'Avarias de Equipamento';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('maintenanceOrder.os_number')
                    ->label('OS')
                    ->searchable(),
                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Ativo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('severity')
                    ->label('Severidade')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        EquipmentDamage::SEVERITY_LEVE => 'Leve',
                        EquipmentDamage::SEVERITY_MODERADA => 'Moderada',
                        EquipmentDamage::SEVERITY_GRAVE => 'Grave / Perda Total',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        EquipmentDamage::SEVERITY_GRAVE => 'danger',
                        EquipmentDamage::SEVERITY_MODERADA => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\IconColumn::make('requires_replacement')
                    ->label('Troca?')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                Tables\Columns\TextColumn::make('reportedBy.name')
                    ->label('Reportado por'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        EquipmentDamage::STATUS_AGUARDANDO_ASSINATURA_CLIENTE => 'Aguardando Assinatura do Cliente',
                        EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR => 'Aguardando Revisão do Supervisor',
                        EquipmentDamage::STATUS_AGUARDANDO_COMERCIAL => 'Aguardando Comercial',
                        EquipmentDamage::STATUS_EM_COBRANCA => 'Em Cobrança',
                        EquipmentDamage::STATUS_RESOLVIDO => 'Resolvido',
                        EquipmentDamage::STATUS_CANCELADO => 'Cancelado',
                    ]),
                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        EquipmentDamage::SEVERITY_LEVE => 'Leve',
                        EquipmentDamage::SEVERITY_MODERADA => 'Moderada',
                        EquipmentDamage::SEVERITY_GRAVE => 'Grave / Perda Total',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipmentDamages::route('/'),
            'view' => Pages\ViewEquipmentDamage::route('/{record}'),
        ];
    }
}

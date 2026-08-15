<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentAllocationResource\Pages;
use App\Models\EquipmentAllocation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * blocked/blocked_reason nunca aparecem no form -- sao decididos pela
 * trigger de banco no momento do insert/update, ver migration
 * create_equipment_allocations_table. Aqui so' mostramos o resultado.
 */
class EquipmentAllocationResource extends BaseResource
{
    protected static ?string $model = EquipmentAllocation::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'Departamento Pessoal';

    protected static ?string $navigationLabel = 'Alocação de Equipamento';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('employee_id')
                ->label('Colaborador')
                ->relationship('employee', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('asset_id')
                ->label('Ativo')
                ->relationship('asset', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\DateTimePicker::make('starts_at')->label('Início'),
            Forms\Components\DateTimePicker::make('ends_at')->label('Fim'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')->label('Colaborador')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('asset.name')->label('Ativo')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('blocked')
                    ->label('Bloqueada')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('blocked_reason')
                    ->label('Motivo do bloqueio')
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('starts_at')->label('Início')->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('ends_at')->label('Fim')->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('blocked')->label('Bloqueada'),
            ])
            ->actions([
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
            'index' => Pages\ListEquipmentAllocations::route('/'),
            'create' => Pages\CreateEquipmentAllocation::route('/create'),
            'edit' => Pages\EditEquipmentAllocation::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceActionTypeResource\Pages;
use App\Models\MaintenanceActionType;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/** Tipos de ação da manutenção: a empresa adiciona, edita e desativa. Não existe exclusão (as OS antigas guardam o que foi feito). */
class MaintenanceActionTypeResource extends BaseResource
{
    protected static ?string $model = MaintenanceActionType::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationLabel = 'Tipos de ação';

    protected static ?string $modelLabel = 'Tipo de ação';

    protected static ?string $pluralModelLabel = 'Tipos de ação';

    protected static ?string $slug = 'tipos-de-acao';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('O que foi feito')->placeholder('Ex.: Troca de óleo, Revisão, Limpeza')->required()->maxLength(120)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', Tenancy::current()?->id))
                ->validationMessages(['unique' => 'Você já tem um tipo de ação com esse nome.']),
            Forms\Components\Toggle::make('is_active')->label('Ativo')->default(true)->helperText('Desative em vez de excluir: as OS antigas continuam mostrando o que foi feito.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Tipo de ação')->searchable()->sortable()->weight('bold'),
                Tables\Columns\IconColumn::make('is_active')->label('Ativo')->boolean(),
                Tables\Columns\TextColumn::make('orders_count')->label('Usado em')->counts('orders')->suffix(' OS'),
            ])
            ->defaultSort('name')
            ->filters([Tables\Filters\TernaryFilter::make('is_active')->label('Situação')->trueLabel('Ativos')->falseLabel('Desativados')->default(true)])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('alternar')
                    ->label(fn (MaintenanceActionType $r) => $r->is_active ? 'Desativar' : 'Reativar')
                    ->icon(fn (MaintenanceActionType $r) => $r->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color('gray')
                    ->action(fn (MaintenanceActionType $r) => $r->update(['is_active' => ! $r->is_active])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaintenanceActionTypes::route('/'),
            'create' => Pages\CreateMaintenanceActionType::route('/create'),
            'edit' => Pages\EditMaintenanceActionType::route('/{record}/edit'),
        ];
    }
}

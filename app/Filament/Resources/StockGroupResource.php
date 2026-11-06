<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockGroupResource\Pages;
use App\Models\StockGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class StockGroupResource extends BaseResource
{
    protected static ?string $model = StockGroup::class;

    protected static ?string $modelLabel = 'Grupo de estoque';

    protected static ?string $pluralModelLabel = 'Grupos de Estoque';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationGroup = 'Materiais e Peças';

    protected static ?string $navigationParentItem = 'Gestão de Estoque';

    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome do grupo')->helperText('Ex.: Administração e escritório, Equipamentos, Veículos, Manutenção, Logística.')->required()->maxLength(120),
            Forms\Components\ColorPicker::make('color')->label('Cor'),
            Forms\Components\TextInput::make('sort_order')->label('Ordem')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->label('Ativo')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('color')->label('Cor'),
                Tables\Columns\TextColumn::make('name')->label('Grupo')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('materials_count')->label('Materiais')->counts('materials'),
                Tables\Columns\IconColumn::make('is_active')->label('Ativo')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->emptyStateHeading('Nenhum grupo ainda')
            ->emptyStateDescription('Ao abrir esta tela pela primeira vez os grupos padrão são criados para a sua empresa.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageStockGroups::route('/')];
    }
}

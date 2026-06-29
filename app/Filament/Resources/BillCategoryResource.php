<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\BillCategoryResource\Pages;
use App\Models\BillCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

#[BelongsToFeature('bill_categories')]
class BillCategoryResource extends Resource
{
    protected static ?string $model = BillCategory::class;

    // 🚀 CORREÇÃO CIRÚRGICA: Adicionada a ? para bater exatamente com a tipagem da classe pai do Filament
    protected static ?string $tenantRelationshipName = 'billCategories';


    protected static ?string $navigationIcon = 'heroicon-o-tag';

    // Posiciona o link no bloco financeiro da sua árvore de menus
    protected static ?string $navigationGroup = 'GESTÃO FINANCEIRA';

    protected static ?string $navigationLabel = 'Categorias de Contas a Pagar';

    protected static ?string $modelLabel = 'Categoria de Conta a Pagar';
    protected static ?string $pluralModelLabel = 'Categorias de Contas a Pagar';

    // Força a rota do link lateral a ignorar o antigo /categories
    protected static ?string $slug = 'bill-categories';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação da Categoria')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome da Categoria de Conta')
                            ->placeholder('Ex: Energia Elétrica, Manutenção Terceirizada, Insumos')
                            ->required()
                            ->maxLength(255),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome da Categoria')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Cadastrado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListBillCategories::route('/'),
            'create' => Pages\CreateBillCategory::route('/create'),
            'edit' => Pages\EditBillCategory::route('/{record}/edit'),
        ];
    }
}
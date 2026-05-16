<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialCategoryResource\Pages;
use App\Models\MaterialCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

class MaterialCategoryResource extends Resource
{ 
    // AJUSTE: Mudado para true para aparecer no menu
    protected static bool $shouldRegisterNavigation = true; 
    
    protected static ?string $model = MaterialCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    
    // AJUSTE: Alinhado com o grupo que aparece na sua imagem image_9ce6a4.jpg
    protected static ?string $navigationGroup = 'GESTÃO DE MATERIAIS';
    protected static ?string $navigationLabel = 'Categorias de Materiais';
    
    protected static ?string $modelLabel = 'Categoria de Material';
    protected static ?string $pluralModelLabel = 'Categorias de Materiais';

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', Filament::getTenant()->id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação da Categoria')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome da Categoria')
                        ->placeholder('Ex: Filtros, Óleos, Peças Hidráulicas')
                        ->required()
                        ->maxLength(255),
                        
                    Forms\Components\Textarea::make('description')
                        ->label('Descrição / Notas')
                        ->placeholder('Opcional: Detalhe o que compõe esta família de materiais')
                        ->rows(3),
                ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('materials_count')
                    ->label('Materiais Vinculados')
                    ->counts('materials')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
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
            'index' => Pages\ListMaterialCategories::route('/'),
            'create' => Pages\CreateMaterialCategory::route('/create'),
            'edit' => Pages\EditMaterialCategory::route('/{record}/edit'),
        ];
    }
}
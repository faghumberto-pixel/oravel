<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\BranchResource\Pages;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

#[BelongsToFeature('branches')]
class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    // 🚀 OBRIGATÓRIO: Garante o isolamento multi-tenant
    protected static ?string $tenantRelationshipName = 'branches';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2'; // Ícone mais adequado para filiais
    protected static ?string $navigationGroup = 'CONFIGURAÇÕES';
    protected static ?string $navigationLabel = 'Filiais';
    protected static ?string $modelLabel = 'Filial';
    protected static ?string $pluralModelLabel = 'Filiais';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação da Filial')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome da Filial')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('Observações/Endereço')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Filial')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data de Cadastro')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
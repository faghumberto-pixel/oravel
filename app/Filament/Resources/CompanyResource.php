<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\Company;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

#[BelongsToFeature('company')]
class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;


    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Empresa';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Defina os campos do seu formulário aqui quando necessário
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Defina as colunas da sua tabela aqui quando necessário
            ])
            ->filters([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}

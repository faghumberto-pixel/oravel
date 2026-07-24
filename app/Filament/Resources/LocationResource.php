<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;

#[BelongsToFeature('locations')]
class LocationResource extends Resource
{
    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $model = Location::class;

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Localizações';

    protected static ?string $modelLabel = 'Localização';

    protected static ?string $pluralModelLabel = 'Localizações';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Dados do Local')
                ->schema([
                    TextInput::make('name')
                        ->label('Nome do Local/Filial/Cliente')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('zip_code')
                        ->label('CEP')
                        ->mask('99999-999')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                            $cleanCep = str_replace('-', '', $state);
                            if (strlen($cleanCep) === 8) {
                                $response = Http::get("https://viacep.com.br/ws/{$cleanCep}/json/")->json();
                                if (! isset($response['erro'])) {
                                    $set('address', $response['logradouro']);
                                    $set('city', $response['localidade']);
                                    $set('state', $response['uf']);
                                }
                            }
                        }),

                    TextInput::make('address')->label('Endereço')->required(),
                    TextInput::make('city')->label('Cidade')->required(),
                    TextInput::make('state')->label('UF')->required()->maxLength(2),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('city'),
            Tables\Columns\TextColumn::make('zip_code'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}

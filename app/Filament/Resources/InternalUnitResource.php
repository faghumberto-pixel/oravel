<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InternalUnitResource\Pages;
use App\Models\InternalUnit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class InternalUnitResource extends Resource
{ 
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $model = InternalUnit::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Unidades Internas';
    protected static ?string $navigationGroup = 'GESTAO DE ATIVOS';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', Auth::user()->tenant_id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação da Unidade')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome da Unidade/Filial')
                        ->required()
                        ->maxLength(255),
                ]),

            Forms\Components\Section::make('Geolocalização para Logística')
                ->description('Dados necessários para cálculo de KM e deslocamento')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('cep')
                            ->label('CEP')
                            ->mask('99999-999'),
                        Forms\Components\TextInput::make('city')
                            ->label('Cidade'),
                        Forms\Components\TextInput::make('state')
                            ->label('UF')
                            ->maxLength(2),
                    ]),
                    Forms\Components\TextInput::make('address')
                        ->label('Endereço Completo')
                        ->columnSpanFull(),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->label('Latitude')
                            ->numeric(),
                        Forms\Components\TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric(),
                    ]),
                    // Pequena ajuda visual: Link para validar no mapa
                    Forms\Components\Placeholder::make('map_link')
                        ->label('Visualizar no Mapa')
                        ->content(fn ($record) => $record && $record->latitude && $record->longitude 
                            ? new \Illuminate\Support\HtmlString("<a href='https://www.google.com/maps?q={$record->latitude},{$record->longitude}' target='_blank' style='color: #deff9a;'>Ver no Google Maps</a>")
                            : 'Insira lat/long para habilitar link'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Cidade/UF')
                    ->getStateUsing(fn ($record) => $record->city . '/' . $record->state),
                Tables\Columns\TextColumn::make('address')
                    ->label('Endereço')
                    ->limit(30),
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
            'index' => Pages\ListInternalUnits::route('/'),
            'create' => Pages\CreateInternalUnit::route('/create'),
            'edit' => Pages\EditInternalUnit::route('/{record}/edit'),
        ];
    }
}
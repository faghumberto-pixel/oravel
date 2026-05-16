<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartsRequestResource\Pages;
use App\Models\PartsRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartsRequestResource extends Resource
{
    protected static ?string $model = PartsRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Fila de Logística';
    protected static ?string $navigationGroup = 'GESTÃO DE MANUTENÇÃO';
    protected static ?int $navigationSort = 3;

    protected static ?string $tenantRelationshipName = 'partsRequests';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Gerenciamento da Solicitação')->schema([
                Forms\Components\Select::make('maintenance_order_id')
                    ->label('Ordem de Serviço')
                    ->relationship('maintenanceOrder', 'os_number')
                    ->disabled(),
                
                Forms\Components\TextInput::make('part_description')
                    ->label('Descrição da Peça')
                    ->readOnly(),

                Forms\Components\TextInput::make('quantity')
                    ->label('Quantidade')
                    ->numeric()
                    ->readOnly(),

                Forms\Components\Select::make('status')
                    ->label('Status da Logística')
                    ->options([
                        'pendente' => 'Pendente',
                        'pedida' => 'Peça Comprada/Pedida',
                        'entregue' => 'Entregue ao Técnico',
                    ])
                    ->required()
                    ->native(false),
            ])->columns(2)
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Solicitado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('maintenanceOrder.os_number')
                    ->label('Nº OS')
                    ->searchable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('part_description')
                    ->label('Peça')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Situação')
                    ->colors([
                        'danger' => 'pendente',
                        'warning' => 'pedida',
                        'success' => 'entregue',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Técnico'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()->label('Atualizar'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePartsRequests::route('/'),
        ];
    }
}
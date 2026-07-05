<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\PartsRequestResource\Pages;
use App\Models\PartsRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

#[BelongsToFeature('parts_request')]
class PartsRequestResource extends Resource
{
    protected static ?string $model = PartsRequest::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationLabel = 'Solicitações de peças';

    protected static ?int $navigationSort = 13;

    protected static ?string $tenantRelationshipName = 'partsRequests';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Gerenciamento da Solicitação')->schema([
                Forms\Components\Select::make('maintenance_order_id')
                    ->label('Ordem de Serviço')
                    ->relationship('maintenanceOrder', 'os_number')
                    ->disabled(),

                Forms\Components\Select::make('material_id')
                    ->label('Material')
                    ->relationship('material', 'name')
                    ->disabled(),

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
            ])->columns(2),
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

                Tables\Columns\TextColumn::make('material.name')
                    ->label('Material')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd.'),

                Tables\Columns\TextColumn::make('cost_at_time')
                    ->label('Custo')
                    ->money('BRL'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Situação')
                    ->colors([
                        'danger' => 'pendente',
                        'warning' => 'pedida',
                        'success' => 'entregue',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pendente' => 'Pendente',
                        'pedida' => 'Peça Comprada/Pedida',
                        'entregue' => 'Entregue ao Técnico',
                    ]),
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

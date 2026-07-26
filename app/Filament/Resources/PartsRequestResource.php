<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartsRequestResource\Pages;
use App\Models\MaterialRequest;
use App\Models\PartsRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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

                Tables\Columns\IconColumn::make('converted_to_material_request_id')
                    ->label('Requisição Formal')
                    ->boolean()
                    ->trueIcon('heroicon-o-clipboard-document-check')
                    ->falseIcon('heroicon-o-minus'),
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

                // Link explicito entre os dois sistemas, sem fundir tabelas
                // -- ver comentario na migration 2026_07_14_100400. Um
                // PartsRequest so' pode ser convertido uma vez.
                Tables\Actions\Action::make('converter_em_requisicao')
                    ->label('Converter em Requisição')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('info')
                    ->visible(fn (PartsRequest $record) => $record->converted_to_material_request_id === null)
                    ->action(function (PartsRequest $record) {
                        $materialRequest = MaterialRequest::create([
                            'tenant_id' => $record->tenant_id,
                            'user_id' => auth()->id(),
                            'maintenance_order_id' => $record->maintenance_order_id,
                            'origin' => MaterialRequest::ORIGIN_CONVERSAO_PARTS_REQUEST,
                            'status' => MaterialRequest::STATUS_RASCUNHO,
                            'requested_at' => now(),
                            'notes' => 'Gerada automaticamente a partir da Solicitação de Peças por estoque baixo.',
                        ]);

                        $materialRequest->items()->create([
                            'material_id' => $record->material_id,
                            'quantity' => $record->quantity,
                            'cost_price' => $record->cost_at_time,
                        ]);

                        $record->update(['converted_to_material_request_id' => $materialRequest->id]);

                        Notification::make()
                            ->title('Requisição de compra criada')
                            ->success()
                            ->actions([
                                NotificationAction::make('ver')
                                    ->button()
                                    ->url(MaterialRequestResource::getUrl('edit', ['record' => $materialRequest])),
                            ])
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePartsRequests::route('/'),
        ];
    }
}

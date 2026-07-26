<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\PurchaseOrderResource\RelationManagers\ItemsRelationManager;
use App\Models\PurchaseOrder;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ordem de Compra formal -- nasce via "Gerar Ordem de Compra" em
 * MaterialRequestResource (a partir de uma cotacao selecionada), ou
 * pode ser criada avulsa aqui (compra direta, sem requisicao formal).
 * status e' recalculado sozinho conforme o recebimento acontece
 * (PurchaseOrder::recalculateStatus(), ver Fase de Recebimento) -- nao
 * editavel a mao.
 */
class PurchaseOrderResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $modelLabel = 'Ordem de Compra';

    protected static ?string $pluralModelLabel = 'Ordens de Compra';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Ordem de Compra')
                ->schema([
                    Forms\Components\Select::make('supplier_id')
                        ->label('Fornecedor')
                        ->relationship('supplier', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('material_request_id')
                        ->label('Requisição de Origem')
                        ->relationship('materialRequest', 'id')
                        ->disabled()
                        ->visible(fn ($record) => $record?->material_request_id !== null),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(PurchaseOrder::statusOptions())
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('total_value')
                        ->label('Valor Total')
                        ->numeric()
                        ->prefix('R$')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\DatePicker::make('expected_delivery_date')
                        ->label('Previsão de Entrega'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aberta em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornecedor'),
                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valor Total')
                    ->money('BRL'),
                Tables\Columns\TextColumn::make('expected_delivery_date')
                    ->label('Previsão')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => PurchaseOrder::STATUS_ABERTA,
                        'warning' => PurchaseOrder::STATUS_PARCIALMENTE_RECEBIDA,
                        'success' => PurchaseOrder::STATUS_RECEBIDA,
                        'danger' => PurchaseOrder::STATUS_CANCELADA,
                    ])
                    ->formatStateUsing(fn (string $state) => PurchaseOrder::statusOptions()[$state] ?? $state),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(PurchaseOrder::statusOptions()),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}

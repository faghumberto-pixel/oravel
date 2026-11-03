<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\PurchaseOrderResource\RelationManagers\ItemsRelationManager;
use App\Models\PurchaseOrder;
use App\Support\CrmPalette;
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
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $modelLabel = 'Ordem de Compra';

    protected static ?string $pluralModelLabel = 'Ordens de Compra';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Materiais e Peças';

    protected static ?string $navigationParentItem = 'Gestão de Compras';

    protected static ?int $navigationSort = 1;

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

    /** Entrega atrasada: ordem ainda aberta/parcial com previsao no passado. */
    public static function entregaAtrasada(PurchaseOrder $o): bool
    {
        return in_array($o->status, [PurchaseOrder::STATUS_ABERTA, PurchaseOrder::STATUS_PARCIALMENTE_RECEBIDA], true)
            && $o->expected_delivery_date !== null
            && $o->expected_delivery_date->lt(today());
    }

    /** Cor do cartao por significado: atrasada = vermelho; senao pela situacao da ordem. */
    public static function corDaOrdem(PurchaseOrder $o): string
    {
        if (self::entregaAtrasada($o)) {
            return 'red';
        }

        return match ($o->status) {
            PurchaseOrder::STATUS_ABERTA => 'blue',
            PurchaseOrder::STATUS_PARCIALMENTE_RECEBIDA => 'amber',
            PurchaseOrder::STATUS_RECEBIDA => 'emerald',
            default => 'gray',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            // Lista em cartoes (padrao do CRM Comercial): borda na cor da situacao; vermelha = entrega atrasada.
            ->recordClasses(fn (PurchaseOrder $record) => 'border-s-4 hover:!bg-transparent '
                .CrmPalette::cor(self::corDaOrdem($record))['border'].' '
                .CrmPalette::cor(self::corDaOrdem($record))['soft'])
            ->contentGrid(['md' => 2, '2xl' => 3])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('supplier.name')
                            ->label('Fornecedor')
                            ->weight('bold')->size('lg')->color('crmOrange')
                            ->placeholder('Sem fornecedor'),
                        Tables\Columns\BadgeColumn::make('status')
                            ->label('Status')
                            ->grow(false)
                            ->colors([
                                'gray' => PurchaseOrder::STATUS_ABERTA,
                                'warning' => PurchaseOrder::STATUS_PARCIALMENTE_RECEBIDA,
                                'success' => PurchaseOrder::STATUS_RECEBIDA,
                                'danger' => PurchaseOrder::STATUS_CANCELADA,
                            ])
                            ->formatStateUsing(fn (string $state) => PurchaseOrder::statusOptions()[$state] ?? $state),
                    ]),
                    Tables\Columns\TextColumn::make('total_value')
                        ->label('Valor Total')
                        ->money('BRL')
                        ->weight('semibold')->size('lg'),
                    Tables\Columns\TextColumn::make('expected_delivery_date')
                        ->label('Previsão')->icon('heroicon-m-truck')
                        ->color(fn (PurchaseOrder $r) => self::entregaAtrasada($r) ? 'danger' : 'gray')
                        ->formatStateUsing(fn ($state, PurchaseOrder $r): string => 'Entrega prevista para '.$r->expected_delivery_date?->format('d/m/Y').(self::entregaAtrasada($r) ? ' (atrasada)' : ''))
                        ->placeholder('Sem previsão de entrega'),
                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Aberta em')->icon('heroicon-m-calendar-days')->color('gray')
                        ->formatStateUsing(fn ($state, PurchaseOrder $r): string => 'Aberta em '.$r->created_at?->format('d/m/Y H:i'))
                        ->sortable(),
                ])->space(2),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(PurchaseOrder::statusOptions()),
                Tables\Filters\Filter::make('entrega_atrasada')
                    ->label('Entrega atrasada')
                    ->toggle()
                    ->query(fn ($query) => $query
                        ->whereIn('status', [PurchaseOrder::STATUS_ABERTA, PurchaseOrder::STATUS_PARCIALMENTE_RECEBIDA])
                        ->whereNotNull('expected_delivery_date')
                        ->whereDate('expected_delivery_date', '<', today())),
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

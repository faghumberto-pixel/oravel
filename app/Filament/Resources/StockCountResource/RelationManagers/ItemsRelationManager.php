<?php

namespace App\Filament\Resources\StockCountResource\RelationManagers;

use App\Filament\Pages\RastreioDoItemPage;
use App\Models\StockCountItem;
use App\Services\Estoque\ContagemFisica;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/** Folha de contagem + confronto: esperado x contado, quem contou e quando. No computador a contagem tambem pode ser feita aqui. */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Folha de contagem e conferência';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('item_name')
            ->columns([
                Tables\Columns\TextColumn::make('location_code')->label('Endereço')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('item_name')->label('Item')->searchable()->weight('bold')->description(fn (StockCountItem $r) => trim(($r->item_sku ?: '').' · '.($r->group_name ?: ''), ' ·')),
                Tables\Columns\TextColumn::make('expected_quantity')->label('Sistema')->numeric(3, ',', '.'),
                Tables\Columns\TextColumn::make('counted_quantity')->label('Contado')->numeric(3, ',', '.')->placeholder('não contado'),
                Tables\Columns\TextColumn::make('diferenca')->label('Diferença')->state(fn (StockCountItem $r) => $r->diferenca())->numeric(3, ',', '.')->badge()->placeholder('—')
                    ->color(fn ($state) => $state === null ? 'gray' : (abs((float) $state) < 0.001 ? 'success' : ((float) $state < 0 ? 'danger' : 'warning'))),
                Tables\Columns\TextColumn::make('counted_by_name')->label('Contou')->placeholder('—'),
                Tables\Columns\TextColumn::make('counted_at')->label('Quando')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
            ])
            ->defaultSort('location_code')
            ->groups([Tables\Grouping\Group::make('location_code')->label('Endereço')->collapsible()])
            ->filters([
                Tables\Filters\SelectFilter::make('situacao')->label('Situação')->options(['nao' => 'Não contados', 'contados' => 'Contados', 'divergentes' => 'Com divergência', 'iguais' => 'Sem divergência'])
                    ->query(fn (Builder $q, array $data) => match ($data['value'] ?? null) {
                        'nao' => $q->whereNull('counted_quantity'),
                        'contados' => $q->whereNotNull('counted_quantity'),
                        'divergentes' => $q->whereNotNull('counted_quantity')->whereRaw('abs(counted_quantity - expected_quantity) >= 0.001'),
                        'iguais' => $q->whereNotNull('counted_quantity')->whereRaw('abs(counted_quantity - expected_quantity) < 0.001'),
                        default => $q,
                    }),
                Tables\Filters\SelectFilter::make('stock_location_id')->label('Endereço')->options(fn () => $this->getOwnerRecord()->items()->whereNotNull('stock_location_id')->distinct()->pluck('location_code', 'stock_location_id')),
                Tables\Filters\SelectFilter::make('group_name')->label('Grupo')->options(fn () => $this->getOwnerRecord()->items()->whereNotNull('group_name')->distinct()->pluck('group_name', 'group_name')),
            ])
            ->actions([
                Tables\Actions\Action::make('contar')->label(fn (StockCountItem $r) => $r->counted_quantity === null ? 'Contar' : 'Recontar')->icon('heroicon-o-pencil-square')
                    ->visible(fn (StockCountItem $r) => $r->count->aberta())
                    ->form([
                        Forms\Components\TextInput::make('quantidade')->label('Quantidade contada')->numeric()->minValue(0)->required(),
                        Forms\Components\TextInput::make('nota')->label('Observação')->maxLength(200),
                    ])
                    ->action(function (StockCountItem $r, array $data): void {
                        try {
                            app(ContagemFisica::class)->registrar($r, (float) $data['quantidade'], auth()->user(), 'web', $data['nota'] ?? null);
                        } catch (RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('historico')->label('Histórico')->icon('heroicon-o-clock')->color('gray')
                    ->modalHeading(fn (StockCountItem $r) => 'Quem contou: '.$r->item_name)->modalSubmitAction(false)->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (StockCountItem $r) => view('filament.estoque.historico-contagem', ['entradas' => $r->entries()->get(), 'esperado' => $r->expected_quantity])),
                Tables\Actions\Action::make('rastrear')->label('Rastrear')->icon('heroicon-o-magnifying-glass')->color('gray')
                    ->url(fn (StockCountItem $r) => RastreioDoItemPage::getUrl(['item' => $r->item_type.'|'.$r->item_id]))->openUrlInNewTab(),
            ])
            ->paginated([25, 50, 100]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockLocationResource\Pages;
use App\Filament\Resources\StockLocationResource\RelationManagers\PlacementsRelationManager;
use App\Models\InternalUnit;
use App\Models\StockLocation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

class StockLocationResource extends BaseResource
{
    protected static ?string $model = StockLocation::class;

    protected static ?string $modelLabel = 'Endereço do almoxarifado';

    protected static ?string $pluralModelLabel = 'Endereços do Almoxarifado';

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Materiais e Peças';

    protected static ?string $navigationParentItem = 'Gestão de Estoque';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('internal_unit_id')->label('Almoxarifado (matriz/filial)')->options(fn () => InternalUnit::query()->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
            Forms\Components\TextInput::make('aisle')->label('Corredor')->helperText('Ex.: A, B, 1')->required()->maxLength(30),
            Forms\Components\TextInput::make('shelf')->label('Prateleira')->helperText('Ex.: 01, 02')->required()->maxLength(30),
            Forms\Components\TextInput::make('level')->label('Nível')->helperText('Ex.: 1 (chão), 2, 3')->maxLength(30),
            Forms\Components\TextInput::make('position')->label('Posição / caixa')->maxLength(30),
            Forms\Components\TextInput::make('description')->label('Descrição')->maxLength(255)->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->label('Ativo')->default(true),
        ])->columns(2);
    }

    /** Consulta somente leitura (destino do QR da prateleira): onde fica e o que tem, sem formulário de edição. */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('code')->label('Endereço')->weight('bold')->size('lg'),
            TextEntry::make('internalUnit.name')->label('Almoxarifado'),
            TextEntry::make('aisle')->label('Corredor'),
            TextEntry::make('shelf')->label('Prateleira'),
            TextEntry::make('level')->label('Nível')->placeholder('—'),
            TextEntry::make('position')->label('Posição / caixa')->placeholder('—'),
            TextEntry::make('description')->label('Descrição')->placeholder('—')->columnSpanFull(),
            IconEntry::make('is_active')->label('Ativo')->boolean(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Endereço')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('internalUnit.name')->label('Almoxarifado')->sortable(),
                Tables\Columns\TextColumn::make('aisle')->label('Corredor')->sortable(),
                Tables\Columns\TextColumn::make('shelf')->label('Prateleira'),
                Tables\Columns\TextColumn::make('level')->label('Nível'),
                Tables\Columns\TextColumn::make('placements_count')->label('Itens')->counts('placements')->badge(),
                Tables\Columns\IconColumn::make('is_active')->label('Ativo')->boolean(),
            ])
            ->defaultSort('code')
            ->filters([
                Tables\Filters\SelectFilter::make('internal_unit_id')->label('Almoxarifado')->options(fn () => InternalUnit::query()->orderBy('name')->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('aisle')->label('Corredor')->options(fn () => StockLocation::query()->distinct()->orderBy('aisle')->pluck('aisle', 'aisle')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('gerar')
                    ->label('Gerar endereços em lote')->icon('heroicon-o-squares-plus')
                    ->form([
                        Forms\Components\Select::make('internal_unit_id')->label('Almoxarifado')->options(fn () => InternalUnit::query()->orderBy('name')->pluck('name', 'id'))->required(),
                        Forms\Components\TextInput::make('corredores')->label('Corredores')->helperText('Separe por vírgula. Ex.: A, B, C')->required(),
                        Forms\Components\TextInput::make('prateleiras')->label('Prateleiras por corredor')->numeric()->minValue(1)->maxValue(60)->default(6)->required(),
                        Forms\Components\TextInput::make('niveis')->label('Níveis por prateleira')->numeric()->minValue(1)->maxValue(12)->default(3)->required(),
                    ])
                    ->action(function (array $data): void {
                        $criados = 0;
                        $tenantId = auth()->user()->tenant_id;
                        foreach (array_filter(array_map('trim', explode(',', (string) $data['corredores']))) as $corredor) {
                            for ($p = 1; $p <= (int) $data['prateleiras']; $p++) {
                                for ($n = 1; $n <= (int) $data['niveis']; $n++) {
                                    $e = StockLocation::query()->firstOrCreate(
                                        ['tenant_id' => $tenantId, 'internal_unit_id' => $data['internal_unit_id'], 'code' => StockLocation::montarCodigo($corredor, str_pad((string) $p, 2, '0', STR_PAD_LEFT), (string) $n)],
                                        ['aisle' => $corredor, 'shelf' => str_pad((string) $p, 2, '0', STR_PAD_LEFT), 'level' => (string) $n, 'is_active' => true],
                                    );
                                    $criados += $e->wasRecentlyCreated ? 1 : 0;
                                }
                            }
                        }
                        Notification::make()->title($criados.' endereço(s) criado(s)')->body('Os que já existiam foram mantidos.')->success()->send();
                    }),
                Tables\Actions\Action::make('etiquetas')->label('Imprimir etiquetas (todos)')->icon('heroicon-o-qr-code')->color('gray')
                    ->url(fn () => route('estoque.etiquetas', ['tipo' => 'enderecos', 'modelo' => 'grande']))->openUrlInNewTab(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([
                Tables\Actions\BulkAction::make('etiquetas')->label('Imprimir etiquetas')->icon('heroicon-o-qr-code')
                    ->action(fn ($records, $livewire) => $livewire->js('window.open('.json_encode(route('estoque.etiquetas', ['tipo' => 'enderecos', 'modelo' => 'grande', 'ids' => $records->pluck('id')->all()])).', "_blank")')),
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [PlacementsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockLocations::route('/'),
            'create' => Pages\CreateStockLocation::route('/create'),
            'view' => Pages\ViewStockLocation::route('/{record}'),
            'edit' => Pages\EditStockLocation::route('/{record}/edit'),
        ];
    }
}

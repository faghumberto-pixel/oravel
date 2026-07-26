<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialRequestResource\Pages;
use App\Filament\Resources\MaterialRequestResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\MaterialRequestResource\RelationManagers\QuotationsRelationManager;
use App\Models\MaterialRequest;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Requisicao de compra formal -- ver comentario em App\Models\MaterialRequest.
 * Convive com PartsRequestResource (o alerta automatico de estoque
 * baixo): esta tela e' pra abrir manualmente uma requisicao com varios
 * itens, ou pra receber a conversao de um PartsRequest (botao "Converter
 * em Requisição" em PartsRequestResource).
 */
class MaterialRequestResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = MaterialRequest::class;

    protected static ?string $modelLabel = 'Requisição de Compra';

    protected static ?string $pluralModelLabel = 'Requisições de Compra';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Requisição')
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('Solicitante')
                        ->relationship('user', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->default(fn () => auth()->id())
                        ->required(),
                    Forms\Components\Select::make('maintenance_order_id')
                        ->label('Ordem de Serviço de Origem (opcional)')
                        ->relationship('maintenanceOrder', 'os_number', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->searchable(),
                    Forms\Components\Select::make('requested_for_location_id')
                        ->label('Filial de Destino')
                        ->relationship('requestedForLocation', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                        // Mesmo padrao de GoodsReceiptResource::purchase_order_id --
                        // preenchido quando vem do link da notificacao de
                        // estoque baixo por filial (MaterialStockService).
                        ->default(fn () => request()->query('requested_for_location_id'))
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('priority')
                        ->label('Prioridade')
                        ->options(MaterialRequest::priorityOptions())
                        ->default(MaterialRequest::PRIORITY_NORMAL)
                        ->required()
                        ->native(false),
                    Forms\Components\DatePicker::make('target_delivery_date')
                        ->label('Data Desejada de Entrega'),
                    Forms\Components\TextInput::make('provider_name')
                        ->label('Fornecedor Sugerido (opcional)')
                        ->maxLength(255),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(MaterialRequest::statusOptions())
                        ->default(MaterialRequest::STATUS_RASCUNHO)
                        ->required()
                        ->native(false),
                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('Motivo da Recusa')
                        ->disabled()
                        ->visible(fn (?MaterialRequest $record) => $record?->rejection_reason)
                        ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Solicitante'),
                Tables\Columns\TextColumn::make('maintenanceOrder.os_number')
                    ->label('OS de Origem')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('requestedForLocation.name')
                    ->label('Filial')
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('priority')
                    ->label('Prioridade')
                    ->colors([
                        'gray' => MaterialRequest::PRIORITY_NORMAL,
                        'warning' => MaterialRequest::PRIORITY_URGENTE,
                        'danger' => MaterialRequest::PRIORITY_CRITICO,
                    ])
                    ->formatStateUsing(fn (string $state) => MaterialRequest::priorityOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Itens')
                    ->counts('items'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => MaterialRequest::STATUS_RASCUNHO,
                        'warning' => [MaterialRequest::STATUS_AGUARDANDO_APROVACAO, MaterialRequest::STATUS_COTADO, MaterialRequest::STATUS_A_CAMINHO],
                        'success' => [MaterialRequest::STATUS_APROVADA, MaterialRequest::STATUS_ENTREGUE],
                        'danger' => MaterialRequest::STATUS_RECUSADA,
                    ])
                    ->formatStateUsing(fn (string $state) => MaterialRequest::statusOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Aprovada por')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(MaterialRequest::statusOptions()),
                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridade')
                    ->options(MaterialRequest::priorityOptions()),
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
            QuotationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterialRequests::route('/'),
            'create' => Pages\CreateMaterialRequest::route('/create'),
            'edit' => Pages\EditMaterialRequest::route('/{record}/edit'),
        ];
    }
}

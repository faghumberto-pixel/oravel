<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropostaComercialResource\Pages;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\PropostaComercialTemplate;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * Tela do time Comercial pra revisar propostas enviadas pelo vendedor de
 * campo, e (desde 2026-08-28) também criar uma proposta pelo desktop --
 * o wizard mobile (App\Livewire\PropostaComercialMobile) continua
 * existindo e funcionando igual, esta é uma segunda porta de entrada,
 * não substitui a primeira. Edição continua só pelo vendedor no wizard
 * mobile enquanto a proposta está em rascunho -- não há EditPropostaComercial.
 */
class PropostaComercialResource extends BaseResource
{
    protected static ?string $model = PropostaComercial::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $modelLabel = 'Proposta Comercial';

    protected static ?string $pluralModelLabel = 'Propostas Comerciais';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('client_id')
                        ->label('Cliente')
                        ->options(fn () => Client::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->helperText('Obrigatório antes de enviar ao Comercial -- pode ficar em branco enquanto rascunho.'),
                    Forms\Components\Select::make('seller_user_id')
                        ->label('Vendedor')
                        ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->default(fn () => auth()->id())
                        ->required(),
                ]),

            Forms\Components\Section::make('Itens')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('type')
                                ->label('Tipo')
                                ->options(PropostaComercialItem::typeLabels())
                                ->default(PropostaComercialItem::TYPE_EQUIPAMENTO)
                                ->live()
                                ->required(),
                            Forms\Components\Select::make('asset_category_id')
                                ->label('Categoria do Equipamento')
                                ->options(fn () => AssetCategory::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->visible(fn (Forms\Get $get) => $get('type') === PropostaComercialItem::TYPE_EQUIPAMENTO)
                                ->required(fn (Forms\Get $get) => $get('type') === PropostaComercialItem::TYPE_EQUIPAMENTO),
                            Forms\Components\TextInput::make('description')
                                ->label('Descrição')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('quantity')
                                ->label('Quantidade')
                                ->numeric()
                                ->default(1)
                                ->minValue(0.01)
                                ->required(),
                            Forms\Components\TextInput::make('unit_price')
                                ->label('Valor Unitário')
                                ->numeric()
                                ->prefix('R$')
                                ->minValue(0)
                                ->required(),
                            Forms\Components\TextInput::make('unit_period')
                                ->label('Período')
                                ->placeholder('ex: mensal, diária')
                                ->maxLength(191),
                            Forms\Components\DatePicker::make('start_date')->label('Início'),
                            Forms\Components\DatePicker::make('end_date')->label('Fim')->afterOrEqual('start_date'),
                            Forms\Components\Textarea::make('item_terms')
                                ->label('Observações do Item')
                                ->maxLength(2000)
                                ->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->defaultItems(1)
                        ->addActionLabel('Adicionar Item'),
                ]),

            Forms\Components\Section::make('Termos')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('proposta_comercial_template_id')
                        ->label('Aplicar Template')
                        ->options(fn () => PropostaComercialTemplate::where('tenant_id', Tenancy::current()?->id)->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                            if (! $state) {
                                return;
                            }

                            $template = PropostaComercialTemplate::find($state);
                            $set('terms', $template?->default_terms);

                            if ($template?->default_valid_days) {
                                $set('valid_until', now()->addDays($template->default_valid_days)->toDateString());
                            }
                        }),
                    Forms\Components\DatePicker::make('valid_until')
                        ->label('Válida até')
                        ->columnSpan(2),
                    Forms\Components\Textarea::make('terms')
                        ->label('Termos')
                        ->columnSpanFull()
                        ->rows(4),
                ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Identificação')
                ->columns(3)
                ->schema([
                    TextEntry::make('client.name')->label('Cliente'),
                    TextEntry::make('sellerUser.name')->label('Vendedor'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => PropostaComercial::statusLabels()[$state] ?? $state),
                    TextEntry::make('valid_until')->label('Válida até')->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('total_value')->label('Valor Total')->money('BRL'),
                    TextEntry::make('sent_at')->label('Enviada em')->dateTime('d/m/Y H:i')->placeholder('—'),
                ]),

            Section::make('Itens')
                ->schema([
                    RepeatableEntry::make('items')
                        ->label('')
                        ->schema([
                            TextEntry::make('type')
                                ->label('Tipo')
                                ->formatStateUsing(fn (string $state) => PropostaComercialItem::typeLabels()[$state] ?? $state),
                            TextEntry::make('description')->label('Descrição'),
                            TextEntry::make('quantity')->label('Qtd.'),
                            TextEntry::make('unit_price')->label('Valor Unit.')->money('BRL'),
                            TextEntry::make('subtotal')->label('Subtotal')->money('BRL'),
                            TextEntry::make('start_date')->label('Início')->date('d/m/Y')->placeholder('—'),
                        ])
                        ->columns(6),
                ]),

            Section::make('Termos')
                ->schema([
                    TextEntry::make('terms')->label('')->placeholder('Sem termos definidos.')->columnSpanFull(),
                ]),

            Section::make('Revisão')
                ->visible(fn (PropostaComercial $record) => $record->status !== PropostaComercial::STATUS_RASCUNHO)
                ->columns(2)
                ->schema([
                    TextEntry::make('reviewedByUser.name')->label('Revisado por')->placeholder('—'),
                    TextEntry::make('reviewed_at')->label('Em')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('rejection_reason')->label('Motivo da Rejeição')->placeholder('—')->columnSpanFull()
                        ->visible(fn (PropostaComercial $record) => in_array($record->status, [
                            PropostaComercial::STATUS_REJEITADA,
                            PropostaComercial::STATUS_RECUSADA_PELO_CLIENTE,
                        ], true)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sellerUser.name')
                    ->label('Vendedor'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => PropostaComercial::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL => 'info',
                        PropostaComercial::STATUS_APROVADA_INTERNA => 'warning',
                        PropostaComercial::STATUS_ACEITA_PELO_CLIENTE => 'success',
                        PropostaComercial::STATUS_RECUSADA_PELO_CLIENTE => 'danger',
                        PropostaComercial::STATUS_REJEITADA => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Itens')
                    ->counts('items'),
                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valor Total')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Enviada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(PropostaComercial::statusLabels()),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('imprimir_selecionadas')
                    ->label('Imprimir Selecionadas')
                    ->icon('heroicon-o-printer')
                    ->action(function (Collection $records) {
                        $ids = $records->pluck('id')->all();

                        return redirect(route('proposta-comercial.print-batch', ['ids' => $ids]));
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPropostaComerciais::route('/'),
            'create' => Pages\CreatePropostaComercial::route('/create'),
            'view' => Pages\ViewPropostaComercial::route('/{record}'),
        ];
    }
}

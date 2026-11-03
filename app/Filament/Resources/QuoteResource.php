<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuoteResource\Pages;
use App\Models\Client;
use App\Models\Material;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CrmPalette;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Tables\Table;

class QuoteResource extends BaseResource
{
    protected static ?string $model = Quote::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão Comercial';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Orçamento';

    protected static ?string $pluralModelLabel = 'Orçamentos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('client_id')
                        ->label('Cliente')
                        ->options(fn () => Client::pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('type')
                        ->label('Tipo')
                        ->options(Quote::typeLabels())
                        ->default(Quote::TYPE_INTERNO)
                        ->required()
                        ->live(),
                    Forms\Components\Select::make('assigned_user_id')
                        ->label('Responsável')
                        ->options(fn () => User::pluck('name', 'id'))
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('third_party_supplier_id')
                        ->label('Responsável Externo (Terceiro)')
                        ->options(fn () => Supplier::pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get) => $get('type') === Quote::TYPE_TERCEIRO)
                        ->required(fn (Get $get) => $get('type') === Quote::TYPE_TERCEIRO),
                    Forms\Components\Textarea::make('technical_report')
                        ->label('Laudo Técnico Prévio')
                        ->helperText('Avaliação do técnico antes da montagem do orçamento -- só faz sentido em orçamento a terceiro.')
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $get('type') === Quote::TYPE_TERCEIRO)
                        ->required(fn (Get $get) => $get('type') === Quote::TYPE_TERCEIRO),
                ]),

            Forms\Components\Section::make('Itens do Orçamento')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('type')
                                ->label('Tipo')
                                ->options(QuoteItem::typeLabels())
                                ->default(QuoteItem::TYPE_PECA)
                                ->required()
                                ->live()
                                ->columnSpan(2),
                            Forms\Components\Select::make('material_id')
                                ->label('Material (Almoxarifado)')
                                ->options(fn () => Material::pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get) => $get('type') === QuoteItem::TYPE_PECA)
                                ->columnSpan(3),
                            Forms\Components\TextInput::make('description')
                                ->label('Descrição')
                                ->required()
                                ->columnSpan(fn (Get $get) => $get('type') === QuoteItem::TYPE_PECA ? 4 : 7),
                            Forms\Components\TextInput::make('quantity')
                                ->label('Qtd.')
                                ->numeric()
                                ->default(1)
                                ->required()
                                ->columnSpan(1),
                            Forms\Components\TextInput::make('unit_price')
                                ->label('Valor Unit.')
                                ->numeric()
                                ->prefix('R$')
                                ->default(0)
                                ->required()
                                ->columnSpan(2),
                        ])
                        ->columns(12)
                        ->addActionLabel('+ Adicionar item')
                        ->defaultItems(1)
                        ->required()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /** Cor do cartao por situacao (nome da paleta de CrmPalette::cor). */
    public const COR_STATUS = [
        Quote::STATUS_RASCUNHO => 'gray', Quote::STATUS_ENVIADO => 'blue', Quote::STATUS_APROVADO => 'emerald',
        Quote::STATUS_REPROVADO => 'red', Quote::STATUS_CONCLUIDO => 'purple',
    ];

    public static function table(Table $table): Table
    {
        return $table
            // Lista em cartoes (padrao do CRM Comercial): borda na cor da situacao do orcamento.
            ->recordClasses(fn (Quote $record) => 'border-s-4 hover:!bg-transparent '
                .CrmPalette::cor(self::COR_STATUS[$record->status] ?? 'gray')['border'].' '
                .CrmPalette::cor(self::COR_STATUS[$record->status] ?? 'gray')['soft'])
            ->contentGrid(['md' => 2, '2xl' => 3])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('client.name')
                            ->label('Cliente')
                            ->searchable()
                            ->sortable()
                            ->weight('bold')->size('lg')->color('crmOrange'),
                        Tables\Columns\TextColumn::make('status')
                            ->label('Status')
                            ->badge()
                            ->grow(false)
                            ->formatStateUsing(fn (string $state) => Quote::statusLabels()[$state] ?? $state)
                            ->color(fn (string $state) => match ($state) {
                                Quote::STATUS_ENVIADO => 'info',
                                Quote::STATUS_APROVADO => 'success',
                                Quote::STATUS_REPROVADO => 'danger',
                                Quote::STATUS_CONCLUIDO => 'primary',
                                default => 'gray',
                            }),
                    ]),
                    Tables\Columns\TextColumn::make('total_value')
                        ->label('Valor Total')
                        ->money('BRL')
                        ->sortable()
                        ->weight('semibold')->size('lg'),
                    Tables\Columns\TextColumn::make('type')
                        ->label('Tipo')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => Quote::typeLabels()[$state] ?? $state)
                        ->color('gray'),
                    Tables\Columns\TextColumn::make('assignedUser.name')
                        ->label('Responsável')->icon('heroicon-m-user')->color('gray')->placeholder('Sem responsável'),
                    Tables\Columns\TextColumn::make('sent_at')
                        ->label('Enviado em')->icon('heroicon-m-paper-airplane')->color('gray')
                        ->formatStateUsing(fn ($state, Quote $r): string => 'Enviado em '.$r->sent_at?->format('d/m/Y H:i'))
                        ->sortable()
                        ->placeholder('Ainda não enviado'),
                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Criado em')->icon('heroicon-m-calendar-days')->color('gray')
                        ->formatStateUsing(fn ($state, Quote $r): string => 'Criado em '.$r->created_at?->format('d/m/Y H:i'))
                        ->sortable(),
                ])->space(2),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Quote::statusLabels()),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(Quote::typeLabels()),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('baixar_pdf')
                        ->label('Ver e imprimir')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('gray')
                        ->url(fn (Quote $record) => route('quotes.pdf', $record))
                        ->openUrlInNewTab(),
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (Quote $record) => $record->status === Quote::STATUS_RASCUNHO),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotes::route('/'),
            'create' => Pages\CreateQuote::route('/create'),
            'edit' => Pages\EditQuote::route('/{record}/edit'),
        ];
    }
}

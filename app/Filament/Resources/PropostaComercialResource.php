<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropostaComercialResource\Pages;
use App\Filament\Resources\PropostaComercialResource\RelationManagers\InteractionsRelationManager;
use App\Models\AggregateItemType;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\PropostaComercialTemplate;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Actions\Action as InfolistAction;
use Filament\Infolists\Components\Actions as InfolistActions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
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

    protected static ?string $navigationParentItem = 'Gestão Comercial';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'Proposta Comercial';

    protected static ?string $pluralModelLabel = 'Propostas Comerciais';

    /** Só o rascunho pode ser editado; depois de enviada ao Comercial a proposta fica travada. */
    public static function canEdit($record): bool
    {
        return $record->status === PropostaComercial::STATUS_RASCUNHO && parent::canEdit($record);
    }

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
                            Forms\Components\Select::make('aggregate_item_type_id')
                                ->label('Tipo do catálogo (opcional)')
                                ->options(fn (Forms\Get $get) => AggregateItemType::query()
                                    ->where('tenant_id', Tenancy::current()?->id)
                                    ->where('category', $get('type'))
                                    ->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->helperText('Liga a venda ao cadastro de Itens Agregados, para o custo entrar no contrato.')
                                ->visible(fn (Forms\Get $get) => in_array($get('type'), [PropostaComercialItem::TYPE_ACESSORIO, PropostaComercialItem::TYPE_INSUMO], true)),
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
                            $set('cabecalho', $template?->cabecalho);
                            $set('campos', $template?->campos ?? []);

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
                    Forms\Components\Textarea::make('cabecalho')
                        ->label('Cabeçalho da empresa')
                        ->helperText('Aparece no topo da proposta enviada ao cliente. Vem do template.')
                        ->columnSpanFull()
                        ->rows(3),
                    Forms\Components\Repeater::make('campos')
                        ->label('Campos da proposta')
                        ->helperText('Seções que o cliente vê na proposta (condições de pagamento, prazo, garantia...). Vêm do template e podem ser ajustadas aqui.')
                        ->schema([
                            Forms\Components\TextInput::make('titulo')->label('Título')->required(),
                            Forms\Components\Textarea::make('texto')->label('Texto')->rows(3),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel('Adicionar campo')
                        ->collapsible()
                        ->columnSpanFull(),
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

            /**
             * Antes disso só existia o botão "Ver Solicitação de Locação"
             * (condicionalmente visível no header), fácil de passar
             * despercebido -- pedido explícito do usuário 28/09/2026: a
             * tela da proposta precisa deixar claro, de forma visível, que
             * o equipamento já foi solicitado (mesmo antes do cliente
             * aceitar, ver PropostaComercial::aprovar()).
             */
            Section::make('Equipamento Já Solicitado')
                ->icon('heroicon-o-check-badge')
                ->iconColor('success')
                ->visible(fn (PropostaComercial $record) => filled($record->solicitacao_locacao_id))
                ->schema([
                    TextEntry::make('solicitacaoLocacao.status_comercial')
                        ->label('Status da Solicitação')
                        ->badge(),
                    TextEntry::make('solicitacaoLocacao.data_saida_prevista')
                        ->label('Saída Prevista')
                        ->date('d/m/Y')
                        ->placeholder('—'),
                    InfolistActions::make([
                        InfolistAction::make('abrir_solicitacao')
                            ->label('Abrir Solicitação de Locação')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->url(fn (PropostaComercial $record) => SolicitacaoLocacaoResource::getUrl('edit', ['record' => $record->solicitacao_locacao_id])),
                    ]),
                ])
                ->columns(2),

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

            Section::make('Campos da proposta')
                ->visible(fn (PropostaComercial $record) => ! empty($record->campos))
                ->schema([
                    RepeatableEntry::make('campos')
                        ->label('')
                        ->schema([
                            TextEntry::make('titulo')->label('Título')->weight('bold'),
                            TextEntry::make('texto')->label('Texto')->placeholder('—'),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
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

            Section::make('Avaliação por IA')
                ->visible(fn (PropostaComercial $record) => $record->ai_evaluated_at !== null)
                ->columns(3)
                ->schema([
                    TextEntry::make('ai_evaluation.risco_coerencia.nota')->label('Risco/Coerência (nota)'),
                    TextEntry::make('ai_evaluation.qualidade_clareza.nota')->label('Qualidade/Clareza (nota)'),
                    TextEntry::make('ai_evaluation.probabilidade_fechamento.nota')->label('Prob. Fechamento (nota)'),
                    TextEntry::make('ai_evaluation.risco_coerencia.comentario')->label('Comentário — Risco/Coerência')->columnSpanFull(),
                    TextEntry::make('ai_evaluation.qualidade_clareza.comentario')->label('Comentário — Qualidade/Clareza')->columnSpanFull(),
                    TextEntry::make('ai_evaluation.probabilidade_fechamento.comentario')->label('Comentário — Prob. Fechamento')->columnSpanFull(),
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
                Tables\Actions\Action::make('enviar_comercial')
                    ->label('Enviar para revisão')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (PropostaComercial $record) => $record->status === PropostaComercial::STATUS_RASCUNHO && auth()->user()?->can('update', $record))
                    ->modalHeading('Enviar proposta para revisão?')
                    ->modalDescription('Escolha quem vai revisar. Depois de enviada, a proposta não pode mais ser editada.')
                    ->form([
                        Forms\Components\Select::make('destinatario')
                            ->label('Enviar para')
                            ->options(fn () => PropostaComercial::opcoesDestinatarios())
                            ->default(fn () => PropostaComercial::destinatarioPadrao())
                            ->searchable()
                            ->required()
                            ->helperText('A pessoa escolhida recebe o aviso para revisar a proposta.'),
                    ])
                    ->action(function (PropostaComercial $record, array $data) {
                        abort_unless(auth()->user()?->can('update', $record), 403);

                        try {
                            $record->enviarParaComercial($data['destinatario'] ?? null);
                            Notification::make()->title('Proposta enviada para revisão')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Não foi possível enviar')->body($e->getMessage())->warning()->send();
                        }
                    }),
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
            'edit' => Pages\EditPropostaComercial::route('/{record}/editar'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            InteractionsRelationManager::class,
        ];
    }
}

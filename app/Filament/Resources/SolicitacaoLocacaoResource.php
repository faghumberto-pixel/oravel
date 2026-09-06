<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SolicitacaoLocacaoResource\Pages;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\SolicitacaoLocacao;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class SolicitacaoLocacaoResource extends Resource
{
    protected static ?string $model = SolicitacaoLocacao::class;

    protected static ?string $slug = 'solicitacoes-locacao';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Solicitações de Locação';

    protected static ?string $modelLabel = 'Solicitação de Locação';

    protected static ?string $pluralModelLabel = 'Solicitações de Locação';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão Comercial';

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Injeção de segurança para evitar erro 23502 (Not null violation)
            Forms\Components\Hidden::make('tenant_id')->default(fn () => Tenancy::current()?->id),
            Forms\Components\Hidden::make('user_id')->default(fn () => auth()->id()),

            Forms\Components\Section::make('Dados da Solicitação')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('customer_id')
                        ->label('Cliente')
                        ->relationship('customer', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->label('Nome do Cliente')->required(),
                        ])
                        // Correção: Retorno explícito como string para UUID e injeção de tenant
                        ->createOptionUsing(function (array $data): string {
                            $cliente = Client::create([
                                'name' => $data['name'],
                                'tenant_id' => Tenancy::current()->id,
                            ]);

                            return (string) $cliente->id;
                        })
                        ->live(),

                    Forms\Components\Select::make('contract_id')
                        ->label('Vincular a Contrato')
                        ->options(fn (Forms\Get $get) => Contract::query()
                            ->where('client_id', $get('customer_id'))
                            ->pluck('contract_number', 'id')
                        )
                        ->searchable()
                        ->live()
                        ->visible(fn (Forms\Get $get) => filled($get('customer_id'))),

                    Forms\Components\Select::make('status_comercial')
                        ->label('Status do Aluguel')
                        ->options([
                            'proposta_em_andamento' => 'Proposta em Andamento',
                            'reserva_manutencao' => 'Reservar para Manutenção (Urgente)',
                            'contrato_fechado' => 'Contrato Fechado',
                            'cancelado' => 'Cancelado',
                        ])
                        ->default('proposta_em_andamento')
                        ->required()
                        ->live(),
                ]),

                Forms\Components\Select::make('cancellation_reason_id')
                    ->label('Motivo do Cancelamento')
                    ->relationship('cancellationReason', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                    ->visible(fn (Forms\Get $get) => $get('status_comercial') === 'cancelado')
                    ->required(fn (Forms\Get $get) => $get('status_comercial') === 'cancelado'),

                Forms\Components\Select::make('category_id')
                    ->label('Categoria do Equipamento')
                    ->relationship('category', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    // Muda a categoria: o Ativo específico escolhido antes
                    // pode não pertencer mais a ela -- evita salvar uma
                    // combinação categoria/ativo inconsistente.
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('asset_id', null)),

                Forms\Components\Placeholder::make('disponibilidade')
                    ->label('Disponibilidade nesta categoria')
                    ->content(fn (Forms\Get $get) => static::disponibilidadeContent($get('category_id')))
                    ->columnSpanFull(),

                Forms\Components\Select::make('asset_id')
                    ->label('Equipamento Específico (Patrimônio)')
                    ->helperText('Só mostra ativos desta categoria que não estão locados agora.')
                    ->options(fn (Forms\Get $get) => static::assetOptionsForCategory($get('category_id'), $get('asset_id')))
                    ->searchable()
                    ->disabled(fn (Forms\Get $get) => blank($get('category_id')))
                    ->native(false),

                Forms\Components\Select::make('assets')
                    ->label('Combo / Lote de Ativos (opcional)')
                    ->helperText('Use quando a solicitação envolve mais de um equipamento simultaneamente (ex: gerador + mini-carregadeira, ou um lote de máquinas idênticas). Mostra ativos de qualquer categoria, desde que não estejam locados.')
                    ->relationship('assets', 'name', fn (Builder $query) => $query
                        ->where('tenant_id', Tenancy::current()?->id)
                        ->whereNotIn('status', [Asset::STATUS_LOCADO, Asset::STATUS_RESERVADO]))
                    ->getOptionLabelFromRecordUsing(fn (Asset $asset) => ($asset->patrimonio ?: '—').' — '.$asset->name)
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Forms\Components\Placeholder::make('historico')
                    ->label('Histórico')
                    ->content(fn (?SolicitacaoLocacao $record) => $record
                        ? view('filament.resources.solicitacao-locacao-resource.partials.historico-grid', ['solicitacao' => $record])
                        : 'Disponível depois de salvar a solicitação pela primeira vez.')
                    ->columnSpanFull()
                    ->visible(fn (?SolicitacaoLocacao $record) => $record !== null),

                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\DatePicker::make('data_saida_prevista')
                        ->label(fn (Forms\Get $get) => filled($get('contract_id')) ? 'Data de Saída' : 'Prazo da Reserva')
                        ->helperText(fn (Forms\Get $get) => filled($get('contract_id'))
                            ? null
                            : 'Cliente ainda sem contrato fechado: informe até quando a reserva vale.')
                        ->required(fn (Forms\Get $get) => blank($get('contract_id'))),
                    Forms\Components\TextInput::make('purpose')->label('Finalidade / Obra'),
                ]),

                // So' e' preenchido pelo rollup automatico de EquipmentMovement::
                // recalculateCustoTransporte() quando o despacho (RentalDispatchChecklistMobile)
                // atribui veiculo/KM -- nunca editado a mao aqui.
                Forms\Components\TextInput::make('logistics_cost')
                    ->label('Custo de Transporte (R$)')
                    ->prefix('R$')
                    ->disabled()
                    ->visible(fn (?SolicitacaoLocacao $record) => $record !== null),
            ]),
        ]);
    }

    /**
     * Ativos elegíveis pra uma categoria: mesma categoria (via
     * Asset.asset_category_id, ver migration 2026_07_24_143615) e status
     * diferente de "locado"/"reservado" -- nunca deixa escolher um
     * equipamento que já está comprometido com outro cliente/reserva.
     * Label sempre mostra o patrimônio primeiro.
     *
     * $currentAssetId: mantém o ativo já salvo no registro na lista mesmo
     * que ele não bata mais no filtro (ficou locado depois, categoria não
     * tinha vínculo no backfill etc.) -- sem isso, abrir uma Solicitação
     * antiga pra editar mostraria o campo em branco mesmo com dado salvo.
     *
     * @return array<string, string>
     */
    private static function assetOptionsForCategory(?string $categoryId, ?string $currentAssetId = null): array
    {
        if (! $categoryId) {
            return [];
        }

        $options = Asset::where('tenant_id', Tenancy::current()?->id)
            ->where('asset_category_id', $categoryId)
            ->whereNotIn('status', [Asset::STATUS_LOCADO, Asset::STATUS_RESERVADO])
            ->orderBy('patrimonio')
            ->get()
            ->mapWithKeys(fn (Asset $asset) => [
                $asset->id => ($asset->patrimonio ?: '—').' — '.$asset->name.' ('.ucfirst($asset->status ?? '—').')',
            ]);

        if ($currentAssetId && ! $options->has($currentAssetId)) {
            $current = Asset::find($currentAssetId);
            if ($current) {
                $options->put($current->id, ($current->patrimonio ?: '—').' — '.$current->name.' (atual, fora do filtro)');
            }
        }

        return $options->all();
    }

    /**
     * Resumo de disponibilidade da categoria escolhida: quantos ativos
     * (não locados, contando os em manutenção) e em qual Unidade Interna
     * (filial/matriz) eles estão -- pedido explícito do usuário pra apoiar
     * a decisão de reservar sem precisar abrir outra tela.
     */
    private static function disponibilidadeContent(?string $categoryId): View|string
    {
        if (! $categoryId) {
            return 'Selecione uma categoria para ver quantos equipamentos existem e onde estão.';
        }

        $tenantId = Tenancy::current()?->id;

        $assets = Asset::where('tenant_id', $tenantId)
            ->where('asset_category_id', $categoryId)
            ->with('internalUnit')
            ->get();

        $naoLocados = $assets->whereNotIn('status', [Asset::STATUS_LOCADO, Asset::STATUS_RESERVADO]);

        $porUnidade = $naoLocados
            ->groupBy(fn (Asset $asset) => $asset->internalUnit?->name ?? 'Sem unidade definida')
            ->map->count()
            ->sortDesc();

        return view('filament.resources.solicitacao-locacao-resource.partials.disponibilidade', [
            'total' => $assets->count(),
            'disponiveis' => $naoLocados->where('status', Asset::STATUS_DISPONIVEL)->count(),
            'emManutencao' => $naoLocados->where('status', Asset::STATUS_MANUTENCAO)->count(),
            'naoLocadosTotal' => $naoLocados->count(),
            'porUnidade' => $porUnidade,
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('customer.name')->label('Cliente')->searchable(),
            Tables\Columns\TextColumn::make('category.name')->label('Categoria'),
            Tables\Columns\TextColumn::make('status_comercial')
                ->label('Status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'proposta_em_andamento' => 'warning',
                    'reserva_manutencao' => 'danger',
                    'contrato_fechado' => 'success',
                    'cancelado' => 'gray',
                }),
            Tables\Columns\TextColumn::make('assets_count')
                ->label('Combo')
                ->counts('assets')
                ->formatStateUsing(fn (int $state) => $state > 1 ? "{$state} ativos" : ($state === 1 ? '1 ativo' : '—'))
                ->badge()
                ->color(fn (int $state) => $state > 1 ? 'info' : 'gray'),
            Tables\Columns\IconColumn::make('kit_completo')
                ->label('Kit Completo')
                ->getStateUsing(fn (SolicitacaoLocacao $record) => $record->assets->count() > 1 ? $record->isKitComplete() : null)
                ->icon(fn (?bool $state) => match ($state) {
                    true => 'heroicon-o-check-badge',
                    false => 'heroicon-o-clock',
                    default => 'heroicon-o-minus',
                })
                ->color(fn (?bool $state) => match ($state) {
                    true => 'success',
                    false => 'warning',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('data_saida_prevista')->label('Saída')->date('d/m/Y'),
        ])->filters([
            Tables\Filters\SelectFilter::make('status_comercial')
                ->label('Status')
                ->options([
                    'proposta_em_andamento' => 'Proposta em Andamento',
                    'reserva_manutencao' => 'Reservar para Manutenção (Urgente)',
                    'contrato_fechado' => 'Contrato Fechado',
                    'cancelado' => 'Cancelado',
                ]),
            Tables\Filters\SelectFilter::make('category_id')
                ->label('Categoria')
                ->relationship('category', 'name'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSolicitacoesLocacao::route('/'),
            'create' => Pages\CreateSolicitacaoLocacao::route('/create'),
            'edit' => Pages\EditSolicitacaoLocacao::route('/{record}/edit'),
            'timeline' => Pages\TimelineSolicitacaoLocacao::route('/{record}/timeline'),
        ];
    }
}

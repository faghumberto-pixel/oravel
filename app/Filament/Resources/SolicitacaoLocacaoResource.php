<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\SolicitacaoLocacaoResource\Pages;
use App\Models\Client;
use App\Models\Contract;
use App\Models\SolicitacaoLocacao;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

#[BelongsToFeature('rental_requests')]
class SolicitacaoLocacaoResource extends Resource
{
    protected static ?string $model = SolicitacaoLocacao::class;

    protected static ?string $slug = 'solicitacoes-locacao';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Comercial';

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

                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Select::make('category_id')
                        ->label('Categoria do Equipamento')
                        ->relationship('category', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live(),

                    Forms\Components\Select::make('asset_id')
                        ->label('Equipamento Específico (Série)')
                        ->relationship('asset', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->searchable()
                        ->preload(),
                ]),

                Forms\Components\Select::make('assets')
                    ->label('Combo / Lote de Ativos (opcional)')
                    ->helperText('Use quando a solicitação envolve mais de um equipamento simultaneamente (ex: gerador + mini-carregadeira, ou um lote de máquinas idênticas).')
                    ->relationship('assets', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\DatePicker::make('data_saida_prevista')
                        ->label(fn (Forms\Get $get) => filled($get('contract_id')) ? 'Data de Saída' : 'Prazo da Reserva')
                        ->helperText(fn (Forms\Get $get) => filled($get('contract_id'))
                            ? null
                            : 'Cliente ainda sem contrato fechado: informe até quando a reserva vale.')
                        ->required(fn (Forms\Get $get) => blank($get('contract_id'))),
                    Forms\Components\TextInput::make('purpose')->label('Finalidade / Obra'),
                ]),
            ]),
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
        ];
    }
}

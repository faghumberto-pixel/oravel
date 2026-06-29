<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\SolicitacaoLocacaoResource\Pages;
use App\Models\SolicitacaoLocacao;
use App\Models\Contract;
use App\Models\Asset;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

#[BelongsToFeature('rental_requests')]
class SolicitacaoLocacaoResource extends Resource
{
    protected static ?string $model = SolicitacaoLocacao::class;
    protected static ?string $slug = 'solicitacoes-locacao';
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'GESTÃO COMERCIAL';
    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Injeção de segurança para evitar erro 23502 (Not null violation)
            Forms\Components\Hidden::make('tenant_id')->default(fn () => \App\Support\Tenancy::current()?->id),
            Forms\Components\Hidden::make('user_id')->default(fn () => auth()->id()),

            Forms\Components\Section::make('Dados da Solicitação')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('customer_id')
                        ->label('Cliente')
                        ->relationship('customer', 'name', fn (Builder $query) => $query->where('tenant_id', \App\Support\Tenancy::current()?->id))
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
                                'tenant_id' => \App\Support\Tenancy::current()->id,
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
                        ->visible(fn (Forms\Get $get) => filled($get('customer_id'))),

                    Forms\Components\Select::make('status_comercial')
                        ->label('Status do Aluguel')
                        ->options([
                            'proposta_em_andamento' => 'Proposta em Andamento',
                            'reserva_manutencao'    => 'Reservar para Manutenção (Urgente)',
                            'contrato_fechado'      => 'Contrato Fechado',
                            'cancelado'             => 'Cancelado',
                        ])
                        ->default('proposta_em_andamento')
                        ->required()
                        ->live(),
                ]),

                Forms\Components\Select::make('cancellation_reason_id')
                    ->label('Motivo do Cancelamento')
                    ->relationship('cancellationReason', 'name', fn (Builder $query) => $query->where('tenant_id', \App\Support\Tenancy::current()?->id))
                    ->visible(fn (Forms\Get $get) => $get('status_comercial') === 'cancelado')
                    ->required(fn (Forms\Get $get) => $get('status_comercial') === 'cancelado'),

                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Select::make('category_id')
                        ->label('Categoria do Equipamento')
                        ->relationship('category', 'name', fn (Builder $query) => $query->where('tenant_id', \App\Support\Tenancy::current()?->id))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live(),

                    Forms\Components\Select::make('asset_id')
                        ->label('Equipamento Específico (Série)')
                        ->relationship('asset', 'name', fn (Builder $query) => $query->where('tenant_id', \App\Support\Tenancy::current()?->id))
                        ->searchable()
                        ->preload(),
                ]),

                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\DatePicker::make('data_saida_prevista')->required(),
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
                    'reserva_manutencao'    => 'danger',
                    'contrato_fechado'      => 'success',
                    'cancelado'             => 'gray',
                }),
            Tables\Columns\TextColumn::make('data_saida_prevista')->label('Saída')->date('d/m/Y'),
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
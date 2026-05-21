<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractResource\Pages;
use App\Models\Contract;
use App\Models\Client;
use App\Models\Asset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Tabs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Enums\FiltersLayout;

class ContractResource extends Resource
{ 
    // 🔑 CORREÇÃO 1: Alterado para 'true' para ativar a exibição física do menu na barra lateral do Oravel
    protected static bool $shouldRegisterNavigation = true;
    
    protected static ?string $model = Contract::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationLabel = 'Contratos de Locação';
    protected static ?string $navigationGroup = '--- GESTÃO COMERCIAL ---';
    protected static ?int $navigationSort = 1;

    /**
     * 🛡️ CORREÇÃO 2: MÉTODO DE PROTEÇÃO E VISIBILIDADE DE ROTAS
     * Garante que apenas você (Administrador) enxergue, crie ou altere contratos,
     * ocultando o menu de forma absoluta da sidebar dos técnicos em campo.
     */
    public static function canViewAny(): bool
    {
        return auth()->check() && method_exists(auth()->user(), 'isAdmin') && auth()->user()->isAdmin();
    }

    public static function canCreate(): bool
    {
        return auth()->check() && method_exists(auth()->user(), 'isAdmin') && auth()->user()->isAdmin();
    }

    public static function canEdit($record): bool
    {
        return auth()->check() && method_exists(auth()->user(), 'isAdmin') && auth()->user()->isAdmin();
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && method_exists(auth()->user(), 'isAdmin') && auth()->user()->isAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', Auth::user()->tenant_id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Gestão de Contrato')->tabs([
                
                // --- ABA 1: IDENTIFICAÇÃO E REGRAS DE NEGÓCIO ---
                Tabs\Tab::make('Identificação')->icon('heroicon-m-identification')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        
                        Forms\Components\TextInput::make('contract_number')
                            ->label('Número do Contrato')
                            ->default(fn () => 'CNT-' . strtoupper(uniqid()))
                            ->required()->unique(ignoreRecord: true),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'Draft' => 'Rascunho',
                                'Active' => 'Ativo',
                                'Expired' => 'Expirado',
                                'Cancelled' => 'Cancelado',
                            ])->default('Draft')->required()->native(false),

                        Forms\Components\Select::make('client_id')
                            ->label('Cliente / Locatário')
                            ->relationship('client', 'name', function (Builder $query) {
                                return $query->where('tenant_id', Auth::user()->tenant_id);
                            })
                            ->required()->searchable()->preload()->live(),

                        // REGRA: Somente ativos DISPONÍVEIS podem ser vinculados
                        Forms\Components\Select::make('asset_id')
                            ->label('Ativo / Equipamento Alocado')
                            ->relationship('asset', 'name', function (Builder $query) {
                                return $query->where('tenant_id', Auth::user()->tenant_id)
                                             ->where('status', 'disponivel'); 
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->hint('Apenas ativos disponíveis para locação aparecem aqui.'),
                    ]),
                ]),

                // --- ABA 2: VIGÊNCIA E FINANCEIRO ---
                Tabs\Tab::make('Vigência e Valores')->icon('heroicon-m-banknotes')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data de Início')->required()->native(false),
                        
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data de Término')->native(false),

                        // Campo price conforme a sua última migration
                        Forms\Components\TextInput::make('price')
                            ->label('Valor da Locação (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('billing_day')
                            ->label('Dia de Vencimento')->numeric()->minValue(1)->maxValue(31)->default(10),
                        
                        Forms\Components\Select::make('payment_method')
                            ->label('Forma de Pagamento')
                            ->options([
                                'Boleto' => 'Boleto Bancário',
                                'Pix' => 'PIX',
                                'Transferencia' => 'Transferência',
                            ])->native(false),
                    ]),
                ]),

                // --- ABA 3: TERMOS JURÍDICOS E OPERACIONAIS ---
                Tabs\Tab::make('Termos e Observações')->icon('heroicon-m-document-text')->schema([
                    Forms\Components\RichEditor::make('contract_terms')
                        ->label('Cláusulas Específicas')->columnSpanFull(),
                    
                    Forms\Components\Textarea::make('observations')
                        ->label('Notas Internas')->rows(3)->columnSpanFull(),
                ]),

            ])->columnSpanFull()
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contract_number')
                    ->label('Contrato')->fontFamily('mono')->weight('bold')->searchable(),
                
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente')->searchable()->sortable(),
                
                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Ativo')->searchable(),
                
                Tables\Columns\TextColumn::make('price')
                    ->label('Valor')->money('BRL')->sortable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()->color(fn ($state) => match ($state) {
                        'Active' => 'success',
                        'Draft' => 'gray',
                        default => 'danger',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['Active' => 'Ativo', 'Draft' => 'Rascunho', 'Expired' => 'Expirado']),
            ], layout: FiltersLayout::Dropdown)
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'edit' => Pages\EditContract::route('/{record}/edit'),
            'view' => Pages\ViewContract::route('/{record}'),
        ];
    }
}
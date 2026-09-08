<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierContractResource\Pages;
use App\Models\SupplierContract;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Contrato de fornecimento -- vinculo opcional de um fornecedor a um
 * contrato com vigencia. Ver App\Models\SupplierContract sobre porque nao
 * reaproveita App\Models\Contract (moldado pra locacao a cliente).
 */
class SupplierContractResource extends BaseResource
{
    protected static ?string $model = SupplierContract::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Compras';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Contrato de Fornecimento';

    protected static ?string $pluralModelLabel = 'Contratos de Fornecimento';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Contrato')
                ->schema([
                    Forms\Components\Select::make('supplier_id')
                        ->label('Fornecedor')
                        ->relationship('supplier', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\TextInput::make('contract_number')
                        ->label('Número do Contrato')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(SupplierContract::statusOptions())
                        ->default(SupplierContract::STATUS_ATIVO)
                        ->required()
                        ->native(false),
                    Forms\Components\DatePicker::make('start_date')
                        ->label('Início da Vigência')
                        ->required(),
                    Forms\Components\DatePicker::make('end_date')
                        ->label('Fim da Vigência'),
                    Forms\Components\TextInput::make('value')
                        ->label('Valor do Contrato')
                        ->numeric()
                        ->prefix('R$'),
                    Forms\Components\TextInput::make('payment_terms')
                        ->label('Condição de Pagamento')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('object')
                        ->label('Objeto do Contrato')
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contract_number')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornecedor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Início')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('value')
                    ->label('Valor')
                    ->money('BRL')
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => SupplierContract::STATUS_ATIVO,
                        'warning' => SupplierContract::STATUS_SUSPENSO,
                        'gray' => SupplierContract::STATUS_ENCERRADO,
                    ])
                    ->formatStateUsing(fn (string $state) => SupplierContract::statusOptions()[$state] ?? $state),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(SupplierContract::statusOptions()),
            ])
            ->defaultSort('start_date', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListSupplierContracts::route('/'),
            'create' => Pages\CreateSupplierContract::route('/create'),
            'edit' => Pages\EditSupplierContract::route('/{record}/edit'),
        ];
    }
}

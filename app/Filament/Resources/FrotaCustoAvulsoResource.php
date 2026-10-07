<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaCustoAvulsoResource\Pages;
use App\Models\Asset;
use App\Models\FrotaCustoAvulso;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/** Custos avulsos do veículo (seguro, IPVA, licenciamento, pedágio...), com rateio em meses, que entram no custo total. */
class FrotaCustoAvulsoResource extends BaseResource
{
    protected static ?string $model = FrotaCustoAvulso::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Seguro, IPVA e outros custos';

    protected static ?int $navigationSort = 14;

    protected static ?string $modelLabel = 'Custo avulso';

    protected static ?string $pluralModelLabel = 'Seguro, IPVA e outros custos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)->options(fn () => Asset::opcoesVeiculos())->columnSpanFull(),
            Forms\Components\Select::make('tipo')->label('Tipo')->options(FrotaCustoAvulso::tipoLabels())->required()->native(false),
            Forms\Components\DatePicker::make('data')->label('Data do pagamento')->required()->default(now()),
            Forms\Components\TextInput::make('valor')->label('Valor (R$)')->numeric()->required()->minValue(0.01)->prefix('R$'),
            Forms\Components\TextInput::make('rateio_meses')->label('Ratear em quantos meses')->numeric()->integer()->required()->minValue(1)->maxValue(60)->default(1)
                ->helperText('Seguro/IPVA anual: 12 (o valor é dividido por mês, a partir do mês do pagamento). Custo pontual: 1.'),
            Forms\Components\TextInput::make('descricao')->label('Descrição')->maxLength(191)->columnSpanFull(),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('veiculo'))
            ->defaultSort('data', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->formatStateUsing(fn (string $state) => FrotaCustoAvulso::tipoLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('data')->label('Pagamento')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('valor')->label('Valor')->money('BRL')->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')->label('Total')),
                Tables\Columns\TextColumn::make('rateio_meses')->label('Rateio')->suffix(' mês(es)'),
                Tables\Columns\TextColumn::make('descricao')->label('Descrição')->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ativo_id')->label('Veículo')->options(fn () => Asset::opcoesVeiculos())->searchable(),
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')->options(FrotaCustoAvulso::tipoLabels()),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->emptyStateHeading('Nenhum custo registrado')
            ->emptyStateDescription('Registre aqui o seguro, o IPVA, o licenciamento e outros custos para entrarem no custo total do veículo.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageFrotaCustosAvulsos::route('/')];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaPlanoOleoResource\Pages;
use App\Models\Asset;
use App\Models\FrotaPlanoOleo;
use App\Services\Frota\OleoService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/** Plano de troca de óleo por veículo (por km, por dias, ou os dois: vence o que ocorrer primeiro). */
class FrotaPlanoOleoResource extends BaseResource
{
    protected static ?string $model = FrotaPlanoOleo::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Planos de óleo';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Plano de óleo';

    protected static ?string $pluralModelLabel = 'Planos de óleo';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)
                ->options(fn () => Asset::opcoesVeiculos())->columnSpanFull(),
            Forms\Components\TextInput::make('intervalo_km')->label('Trocar a cada (km)')->numeric()->minValue(1)->placeholder('Ex.: 10000'),
            Forms\Components\TextInput::make('intervalo_dias')->label('Trocar a cada (dias)')->numeric()->minValue(1)->placeholder('Ex.: 180'),
            Forms\Components\TextInput::make('especificacao_oleo')->label('Óleo especificado')->maxLength(191)->placeholder('Ex.: 15W40 CK-4'),
            Forms\Components\TextInput::make('capacidade_litros')->label('Capacidade do cárter (litros)')->numeric()->integer()->minValue(1),
            Forms\Components\Textarea::make('observacao_filtros')->label('Filtros e observações')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('veiculo')->where('ativo', true))
            ->columns([
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('veiculo.name')->label('Veículo'),
                Tables\Columns\TextColumn::make('intervalo_km')->label('A cada')->suffix(' km')->placeholder('—'),
                Tables\Columns\TextColumn::make('intervalo_dias')->label('Ou a cada')->suffix(' dias')->placeholder('—'),
                Tables\Columns\TextColumn::make('especificacao_oleo')->label('Óleo')->placeholder('—'),
                Tables\Columns\TextColumn::make('capacidade_litros')->label('Cárter')->suffix(' L')->placeholder('—'),
            ])
            ->actions([Tables\Actions\EditAction::make()->using(function (FrotaPlanoOleo $record, array $data) {
                try {
                    app(OleoService::class)->salvarPlano($record->veiculo, $data);
                } catch (ValidationException $e) {
                    Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                }

                return $record;
            })])
            ->emptyStateHeading('Nenhum plano de óleo')
            ->emptyStateDescription('Cadastre o plano para o sistema calcular a próxima troca de cada veículo.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageFrotaPlanosOleo::route('/')];
    }
}

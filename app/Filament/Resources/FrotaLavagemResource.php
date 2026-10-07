<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaLavagemResource\Pages;
use App\Models\Asset;
use App\Models\FrotaLavagem;
use App\Services\Frota\LavagemService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Lavagens e limpeza dos veículos, com agenda opcional (repetir a cada N dias). */
class FrotaLavagemResource extends BaseResource
{
    protected static ?string $model = FrotaLavagem::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Lavagens';

    protected static ?int $navigationSort = 23;

    protected static ?string $modelLabel = 'Lavagem';

    protected static ?string $pluralModelLabel = 'Lavagens';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    private static function executar(\Closure $acao, string $sucesso): void
    {
        try {
            $acao();
            Notification::make()->title($sucesso)->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('veiculo'))
            ->defaultSort('realizada_em', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('registrar_lavagem')->label('Registrar lavagem')->icon('heroicon-o-plus')->color('success')
                    ->form([
                        Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)->options(fn () => Asset::opcoesVeiculos()),
                        Forms\Components\DatePicker::make('realizada_em')->label('Data')->default(now())->maxDate(now())->required(),
                        Forms\Components\Select::make('tipo')->label('Tipo')->options(FrotaLavagem::TIPOS)->required()->native(false),
                        Forms\Components\TextInput::make('valor')->label('Valor (R$, opcional)')->numeric()->minValue(0)->prefix('R$'),
                        Forms\Components\TextInput::make('local')->label('Local')->maxLength(191),
                        Forms\Components\TextInput::make('intervalo_dias')->label('Repetir a cada (dias, opcional)')->numeric()->integer()->minValue(1)->maxValue(365)
                            ->helperText('Cria a agenda: se passar da data prevista sem nova lavagem, vira pendência.'),
                        Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2),
                    ])
                    ->action(fn (array $data) => self::executar(fn () => app(LavagemService::class)->registrar(Asset::findOrFail($data['ativo_id']), $data, auth()->user()), 'Lavagem registrada')),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('realizada_em')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->formatStateUsing(fn (string $state) => FrotaLavagem::TIPOS[$state] ?? $state),
                Tables\Columns\TextColumn::make('valor')->label('Valor')->money('BRL')->placeholder('—')->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')->label('Total')),
                Tables\Columns\TextColumn::make('local')->label('Local')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('proxima_prevista')->label('Próxima prevista')->date('d/m/Y')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ativo_id')->label('Veículo')->options(fn () => Asset::opcoesVeiculos())->searchable(),
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')->options(FrotaLavagem::TIPOS),
            ])
            ->emptyStateHeading('Nenhuma lavagem registrada');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFrotaLavagens::route('/')];
    }
}

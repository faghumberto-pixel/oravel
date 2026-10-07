<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaVinculoMotoristaResource\Pages;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaVinculoMotorista;
use App\Services\Frota\VinculoMotoristaService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Motorista titular de cada veículo, com o histórico de quem foi titular e quando. */
class FrotaVinculoMotoristaResource extends BaseResource
{
    protected static ?string $model = FrotaVinculoMotorista::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Motorista titular';

    protected static ?int $navigationSort = 18;

    protected static ?string $modelLabel = 'Vínculo de motorista';

    protected static ?string $pluralModelLabel = 'Motorista titular dos veículos';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['veiculo', 'motorista']))
            ->defaultSort('inicio', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('atribuir')->label('Definir motorista titular')->icon('heroicon-o-plus')->color('success')
                    ->modalDescription('Se o veículo já tem titular, o vínculo anterior é encerrado na data de início do novo.')
                    ->form([
                        Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)->options(fn () => Asset::opcoesVeiculos()),
                        Forms\Components\Select::make('motorista_id')->label('Motorista')->required()->searchable()->native(false)
                            ->options(fn () => FleetDriver::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all()),
                        Forms\Components\DatePicker::make('inicio')->label('A partir de')->default(now())->maxDate(now())->required(),
                        Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2),
                    ])
                    ->action(function (array $data) {
                        try {
                            app(VinculoMotoristaService::class)->atribuir(Asset::findOrFail($data['ativo_id']), $data['motorista_id'], $data['inicio'], $data['observacoes'] ?? null, auth()->user());
                            Notification::make()->title('Titular definido')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('situacao')->label('Situação')->badge()->state(fn (FrotaVinculoMotorista $r) => $r->vigente() ? 'Titular atual' : 'Encerrado')
                    ->color(fn (string $state) => $state === 'Titular atual' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('motorista.name')->label('Motorista')->searchable(),
                Tables\Columns\TextColumn::make('inicio')->label('Desde')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('fim')->label('Até')->date('d/m/Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('observacoes')->label('Observações')->limit(40)->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('vigentes')->label('Só titulares atuais')->toggle()->default()->query(fn (Builder $q) => $q->whereNull('fim')),
                Tables\Filters\SelectFilter::make('ativo_id')->label('Veículo')->options(fn () => Asset::opcoesVeiculos())->searchable(),
                Tables\Filters\SelectFilter::make('motorista_id')->label('Motorista')->options(fn () => FleetDriver::query()->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('encerrar')->label('Encerrar')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (FrotaVinculoMotorista $r) => $r->vigente())
                    ->form([Forms\Components\DatePicker::make('fim')->label('Data de fim')->default(now())->maxDate(now())->required()])
                    ->action(function (FrotaVinculoMotorista $r, array $data) {
                        try {
                            app(VinculoMotoristaService::class)->encerrar($r, $data['fim']);
                            Notification::make()->title('Vínculo encerrado')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ])
            ->emptyStateHeading('Nenhum motorista titular definido')
            ->emptyStateDescription('Use "Definir motorista titular" para ligar um motorista a cada veículo.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFrotaVinculosMotorista::route('/')];
    }
}

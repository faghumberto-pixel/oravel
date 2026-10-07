<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaTagResource\Pages;
use App\Models\Asset;
use App\Models\FrotaTag;
use App\Services\Frota\PedagioService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Tags de pedágio (Sem Parar, ConectCar...) dos veículos: uma ativa por veículo. */
class FrotaTagResource extends BaseResource
{
    protected static ?string $model = FrotaTag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Tags de pedágio';

    protected static ?int $navigationSort = 21;

    protected static ?string $modelLabel = 'Tag de pedágio';

    protected static ?string $pluralModelLabel = 'Tags de pedágio';

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
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('nova_tag')->label('Nova tag')->icon('heroicon-o-plus')->color('success')
                    ->form([
                        Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)->options(fn () => Asset::opcoesVeiculos()),
                        Forms\Components\TextInput::make('numero')->label('Número da tag')->required()->maxLength(60),
                        Forms\Components\Select::make('operadora')->label('Operadora')->options(FrotaTag::operadoraLabels())->required()->native(false),
                        Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2),
                    ])
                    ->action(fn (array $data) => self::executar(fn () => app(PedagioService::class)->criarTag(Asset::findOrFail($data['ativo_id']), $data['numero'], $data['operadora'], $data['observacoes'] ?? null), 'Tag cadastrada')),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('situacao')->label('Situação')->badge()->state(fn (FrotaTag $r) => $r->ativa ? 'Ativa' : 'Cancelada')->color(fn (string $state) => $state === 'Ativa' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('numero')->label('Tag')->searchable(),
                Tables\Columns\TextColumn::make('operadora')->label('Operadora')->formatStateUsing(fn (string $state) => FrotaTag::operadoraLabels()[$state] ?? $state),
            ])
            ->filters([
                Tables\Filters\Filter::make('ativas')->label('Só tags ativas')->toggle()->default()->query(fn (Builder $q) => $q->where('ativa', true)),
                Tables\Filters\SelectFilter::make('ativo_id')->label('Veículo')->options(fn () => Asset::opcoesVeiculos())->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('cancelar')->label('Cancelar tag')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                    ->visible(fn (FrotaTag $r) => $r->ativa)
                    ->action(fn (FrotaTag $r) => self::executar(fn () => app(PedagioService::class)->cancelarTag($r), 'Tag cancelada')),
            ])
            ->emptyStateHeading('Nenhuma tag cadastrada');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFrotaTags::route('/')];
    }
}

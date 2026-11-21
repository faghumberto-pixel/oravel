<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\ObservatorioEnvioResource\Pages;
use App\Jobs\EnviarEventoObservatorio;
use App\Models\ObservatorioEnvio;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Acompanhamento do outbox da integracao com o Observatorio (so' leitura + reenvio). So' super admin. */
class ObservatorioEnvioResource extends Resource
{
    protected static ?string $model = ObservatorioEnvio::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'Prospecção';

    protected static ?int $navigationSort = 9;

    protected static ?string $modelLabel = 'envio ao Observatório';

    protected static ?string $pluralModelLabel = 'Envios ao Observatório';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('created_at')->label('Registrado')->dateTime('d/m/Y H:i')->sortable(),
            Tables\Columns\TextColumn::make('prospect.nome')->label('Empresa')->searchable()->placeholder('(removida)'),
            Tables\Columns\TextColumn::make('pesquisa_titulo')->label('Pesquisa')->wrap(),
            Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => ObservatorioEnvio::STATUS[$state] ?? $state)
                ->color(fn ($state) => ['enviado' => 'success', 'pendente' => 'warning', 'falhou' => 'danger', 'rejeitado' => 'danger', 'versao_desatualizada' => 'danger'][$state] ?? 'gray'),
            Tables\Columns\TextColumn::make('tentativas')->label('Tentativas'),
            Tables\Columns\TextColumn::make('ultimo_erro')->label('Último erro')->limit(60)->tooltip(fn ($record) => $record->ultimo_erro),
            Tables\Columns\TextColumn::make('enviado_em')->label('Enviado')->dateTime('d/m/Y H:i'),
        ])->filters([Tables\Filters\SelectFilter::make('status')->options(ObservatorioEnvio::STATUS)])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('reenviar')->label('Reenviar')->icon('heroicon-o-arrow-path')
                    ->visible(fn (ObservatorioEnvio $r) => $r->status === 'falhou' && $r->payload !== null)
                    ->requiresConfirmation()
                    ->action(function (ObservatorioEnvio $r) {
                        $r->update(['status' => 'pendente', 'tentativas' => 0, 'proxima_tentativa_em' => now()]);
                        EnviarEventoObservatorio::dispatch($r->id);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListObservatorioEnvios::route('/')];
    }
}

<?php

namespace App\Filament\Concerns;

use App\Models\Asset;
use App\Models\FrotaBateria;
use App\Services\Frota\BateriaService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Illuminate\Validation\ValidationException;

/** Ações de bateria (instalar, remover, testar tensão), reaproveitadas na tela de Baterias e na ficha do veículo. */
trait AcoesBateria
{
    protected static function executarBateria(\Closure $acao, string $sucesso): void
    {
        try {
            $acao();
            Notification::make()->title($sucesso)->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }

    protected static function odometroDoVeiculo(?string $ativoId): ?int
    {
        $v = $ativoId ? Asset::find($ativoId)?->odometro_atual : null;

        return $v !== null ? (int) floor((float) $v) : null;
    }

    /** @param  \Closure|null  $bateriaDe  recebe o registro da linha e devolve a FrotaBateria (padrão: o próprio registro) */
    protected static function acaoRemoverBateria(?\Closure $bateriaDe = null): Tables\Actions\Action
    {
        $bateriaDe ??= fn ($registro) => $registro;

        return Tables\Actions\Action::make('remover')
            ->label('Remover')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('danger')
            ->form(fn ($record) => [
                Forms\Components\Select::make('motivo')->label('Motivo')->options(FrotaBateria::motivoRemocaoLabels())->required()->native(false),
                Forms\Components\TextInput::make('odometro')->label('Odômetro do veículo (km)')->numeric()->required()
                    ->default(self::odometroDoVeiculo($bateriaDe($record)->instalacaoAberta?->ativo_id)),
            ])
            ->action(fn ($record, array $data) => self::executarBateria(
                fn () => app(BateriaService::class)->remover($bateriaDe($record), $data['motivo'], (int) $data['odometro']),
                'Bateria removida'
            ));
    }

    protected static function acaoTestarTensao(?\Closure $bateriaDe = null): Tables\Actions\Action
    {
        $bateriaDe ??= fn ($registro) => $registro;

        return Tables\Actions\Action::make('testar')
            ->label('Testar tensão')
            ->icon('heroicon-o-bolt')
            ->form([Forms\Components\TextInput::make('tensao')->label('Tensão (V)')->numeric()->required()->step(0.01)->helperText('Bateria saudável em repouso: cerca de 12,6 V.')])
            ->action(fn ($record, array $data) => self::executarBateria(
                fn () => app(BateriaService::class)->registrarTeste($bateriaDe($record), (float) str_replace(',', '.', (string) $data['tensao'])),
                'Tensão registrada'
            ));
    }
}

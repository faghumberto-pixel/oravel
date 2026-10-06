<?php

namespace App\Filament\Concerns;

use App\Models\Asset;
use App\Models\FrotaPneu;
use App\Services\Frota\PneuPosicoes;
use App\Services\Frota\PneuService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Illuminate\Validation\ValidationException;

/** Ações de pneu (montar, remover, rodízio, recapagem, sulco/pressão) reaproveitadas na tela de Pneus e na ficha do veículo. */
trait AcoesPneu
{
    /** Roda a ação do serviço e mostra o erro de regra em português, sem estourar a tela. */
    protected static function executar(\Closure $acao, string $sucesso): void
    {
        try {
            $acao();
            Notification::make()->title($sucesso)->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }

    /** @return array<string, string> posições livres do veículo (código => rótulo) */
    protected static function posicoesLivres(?string $ativoId): array
    {
        $ativo = $ativoId ? Asset::find($ativoId) : null;
        if (! $ativo) {
            return [];
        }

        $ocupadas = $ativo->pneusMontados()->pluck('posicao')->all();

        return array_diff_key(PneuPosicoes::para($ativo), array_flip($ocupadas));
    }

    protected static function odometroAtual(?string $ativoId): ?int
    {
        $v = $ativoId ? Asset::find($ativoId)?->odometro_atual : null;

        return $v !== null ? (int) floor((float) $v) : null;
    }

    /** @param  \Closure|null  $pneuDe  recebe o registro da linha e devolve o FrotaPneu (padrão: o próprio registro) */
    protected static function acaoRemover(?\Closure $pneuDe = null): Tables\Actions\Action
    {
        $pneuDe ??= fn ($registro) => $registro;

        return Tables\Actions\Action::make('remover')
            ->label('Remover')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('danger')
            ->form(fn ($record) => [
                Forms\Components\Select::make('motivo')->label('Motivo')->options(FrotaPneu::motivoRemocaoLabels())->required()->native(false),
                Forms\Components\TextInput::make('odometro')->label('Odômetro do veículo (km)')->numeric()->required()
                    ->default(fn () => self::odometroAtual($pneuDe($record)->instalacaoAberta?->ativo_id)),
            ])
            ->action(fn ($record, array $data) => self::executar(
                fn () => app(PneuService::class)->remover($pneuDe($record), $data['motivo'], (int) $data['odometro'], auth()->user()),
                'Pneu removido'
            ));
    }

    /** @param  \Closure|null  $pneuDe  recebe o registro da linha e devolve o FrotaPneu (padrão: o próprio registro) */
    protected static function acaoRodizio(?\Closure $pneuDe = null): Tables\Actions\Action
    {
        $pneuDe ??= fn ($registro) => $registro;

        return Tables\Actions\Action::make('rodizio')
            ->label('Rodízio')
            ->icon('heroicon-o-arrows-right-left')
            ->form(function ($record) use ($pneuDe) {
                $pneu = $pneuDe($record);
                $aberta = $pneu->instalacaoAberta;
                $ativo = $aberta?->ativo;

                return [
                    Forms\Components\Select::make('posicao')->label('Nova posição (se estiver ocupada, os dois pneus trocam de lugar)')
                        ->options($ativo ? array_diff_key(PneuPosicoes::para($ativo), [$aberta->posicao => true]) : [])
                        ->required()->native(false),
                    Forms\Components\TextInput::make('odometro')->label('Odômetro do veículo (km)')->numeric()->required()->default(self::odometroAtual($ativo?->id)),
                ];
            })
            ->action(fn ($record, array $data) => self::executar(
                fn () => app(PneuService::class)->rodizio($pneuDe($record), $data['posicao'], (int) $data['odometro'], auth()->user()),
                'Rodízio registrado'
            ));
    }
}

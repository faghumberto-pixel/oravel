<?php

namespace App\Filament\Resources\CrmPropostaResource\Widgets;

use App\Filament\Resources\CrmPropostaResource;
use App\Models\CrmProposta;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Propostas em aberto e aceitas no mês (respeita a visibilidade: vendedor vê só as dele). */
class CrmPropostaStats extends BaseWidget
{
    protected function getStats(): array
    {
        $base = fn () => CrmPropostaResource::getEloquentQuery();

        $abertas = $base()->where('status', CrmProposta::STATUS_ENVIADA);
        $aceitas = $base()->where('status', CrmProposta::STATUS_ACEITA)->where('respondida_em', '>=', now()->startOfMonth());

        return [
            Stat::make('Propostas em aberto', (clone $abertas)->count())->description('R$ '.number_format((float) (clone $abertas)->sum('total'), 2, ',', '.').' aguardando resposta'),
            Stat::make('Aceitas no mês', (clone $aceitas)->count())->description('R$ '.number_format((float) (clone $aceitas)->sum('total'), 2, ',', '.'))->color('success'),
            Stat::make('Rascunhos', $base()->where('status', CrmProposta::STATUS_RASCUNHO)->count()),
        ];
    }
}

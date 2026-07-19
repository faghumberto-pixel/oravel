<?php

namespace App\Filament\Central\Widgets;

use App\Models\SalesLead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesCrmStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $ganhos = SalesLead::where('pipeline_stage', SalesLead::STAGE_GANHO)->get();

        $tempoMedioDias = $ganhos->isNotEmpty()
            ? round($ganhos->avg(fn (SalesLead $lead) => $lead->created_at->diffInDays($lead->updated_at)), 1)
            : null;

        $totalFechados = SalesLead::whereIn('pipeline_stage', [SalesLead::STAGE_GANHO, SalesLead::STAGE_PERDIDO])->count();
        $perdidos = SalesLead::where('pipeline_stage', SalesLead::STAGE_PERDIDO)->count();
        $taxaPerda = $totalFechados > 0 ? round(($perdidos / $totalFechados) * 100, 1) : null;

        // Soma ponderada pela probabilidade de cada estagio -- soma bruta
        // ilusiona o numero, ver App\Models\SalesLead::stageProbabilityWeights().
        $pesos = SalesLead::stageProbabilityWeights();
        $projecao = SalesLead::whereNotNull('estimated_contract_value')
            ->where('pipeline_stage', '!=', SalesLead::STAGE_PERDIDO)
            ->get()
            ->sum(fn (SalesLead $lead) => (float) $lead->estimated_contract_value * ($pesos[$lead->pipeline_stage] ?? 0));

        $ociosos = SalesLead::query()
            ->whereNotIn('pipeline_stage', [SalesLead::STAGE_GANHO, SalesLead::STAGE_PERDIDO])
            ->where(fn ($q) => $q->whereNull('last_interaction_at')
                ->orWhere('last_interaction_at', '<=', now()->subDays(3)))
            ->count();

        return [
            Stat::make('Tempo Médio de Conversão', $tempoMedioDias !== null ? $tempoMedioDias.' dias' : '—')
                ->description('Da criação até o fechamento (leads ganhos)')
                ->color('success'),
            Stat::make('Taxa de Perda por Estágio', $taxaPerda !== null ? $taxaPerda.'%' : '—')
                ->description('Sobre o total de leads fechados')
                ->color('danger'),
            Stat::make('Projeção de Receita no Funil', 'R$ '.number_format($projecao, 2, ',', '.'))
                ->description('Ponderada pela probabilidade de cada estágio')
                ->color('primary'),
            Stat::make('Leads Ociosos', $ociosos)
                ->description('Sem interação há 3+ dias')
                ->color($ociosos > 0 ? 'warning' : 'success'),
        ];
    }
}

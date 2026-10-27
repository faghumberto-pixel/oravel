<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\Quote;

class ConversaoDeOrcamentos extends ComercialBlock
{
    public static function key(): string
    {
        return 'comercial.conversao_orcamentos';
    }

    public static function title(): string
    {
        return 'Conversão de orçamentos';
    }

    public static function subtitle(): string
    {
        return 'Aprovados sobre enviados, 90 dias';
    }

    public static function size(): int
    {
        return 3;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_quotes';
    }

    public static function sourceModel(): string
    {
        return Quote::class;
    }

    public function data(array $config): array
    {
        $porStatus = Quote::query()
            ->where('created_at', '>=', now()->subDays(90))
            ->whereIn('status', [Quote::STATUS_ENVIADO, Quote::STATUS_APROVADO, Quote::STATUS_REPROVADO, Quote::STATUS_CONCLUIDO])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $enviados = (int) $porStatus->sum();
        if ($enviados === 0) {
            return Payload::kpi('—', '', 'Nenhum orçamento enviado no período');
        }

        $aprovados = (int) (($porStatus[Quote::STATUS_APROVADO] ?? 0) + ($porStatus[Quote::STATUS_CONCLUIDO] ?? 0));

        return Payload::kpi(round($aprovados / $enviados * 100).'%', '', $aprovados.' de '.$enviados.' orçamentos');
    }
}

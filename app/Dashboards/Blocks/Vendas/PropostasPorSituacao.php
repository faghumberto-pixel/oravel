<?php

namespace App\Dashboards\Blocks\Vendas;

use App\Dashboards\Payload;
use App\Models\CrmProposta;

class PropostasPorSituacao extends VendasBlock
{
    public static function key(): string
    {
        return 'vendas.propostas_situacao';
    }

    public static function title(): string
    {
        return 'Propostas por situação';
    }

    public static function subtitle(): string
    {
        return 'Últimos 90 dias';
    }

    public static function size(): int
    {
        return 4;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_crm_propostas';
    }

    public static function sourceModel(): string
    {
        return CrmProposta::class;
    }

    public function data(array $config): array
    {
        $por = CrmProposta::query()->where('created_at', '>=', now()->subDays(90))->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $fatias = collect([
            [CrmProposta::STATUS_ENVIADA, 'Aguardando resposta', '#38bdf8'],
            [CrmProposta::STATUS_ACEITA, 'Aceitas', '#16a34a'],
            [CrmProposta::STATUS_RECUSADA, 'Recusadas', '#ef4444'],
            [CrmProposta::STATUS_EXPIRADA, 'Expiradas', '#f59e0b'],
            [CrmProposta::STATUS_RASCUNHO, 'Rascunhos', '#94a3b8'],
        ])->map(fn ($s) => [$s[1], (int) ($por[$s[0]] ?? 0), $s[2]])->filter(fn ($f) => $f[1] > 0)->values()->all();

        return Payload::donut($fatias, (string) $por->sum(), 'propostas');
    }
}

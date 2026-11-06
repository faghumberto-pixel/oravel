<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\Quote;

class OrcamentosPorSituacao extends ComercialBlock
{
    use ComercialIaHelpers;

    public static function key(): string
    {
        return 'comercial.orcamentos_situacao';
    }

    public static function title(): string
    {
        return 'Orçamentos por situação';
    }

    public static function subtitle(): string
    {
        return 'Últimos 90 dias';
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
        $porStatus = Quote::query()->where('created_at', '>=', now()->subDays(90))->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $cores = [Quote::STATUS_RASCUNHO => '#94a3b8', Quote::STATUS_ENVIADO => '#38bdf8', Quote::STATUS_APROVADO => '#16a34a', Quote::STATUS_REPROVADO => '#ef4444', Quote::STATUS_CONCLUIDO => '#a78bfa'];

        $fatias = collect(Quote::statusLabels())->map(fn ($rotulo, $status) => [$rotulo, (int) ($porStatus[$status] ?? 0), $cores[$status] ?? '#94a3b8'])->filter(fn ($f) => $f[1] > 0)->values()->all();

        return Payload::donut($fatias, (string) $porStatus->sum(), 'orçamentos');
    }
}

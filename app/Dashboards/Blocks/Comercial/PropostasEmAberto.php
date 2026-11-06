<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\PropostaComercial;

class PropostasEmAberto extends ComercialBlock
{
    use ComercialIaHelpers;

    public static function key(): string
    {
        return 'comercial.propostas_abertas';
    }

    public static function title(): string
    {
        return 'Propostas em andamento';
    }

    public static function subtitle(): string
    {
        return 'Enviadas ou aprovadas, aguardando o cliente';
    }

    public static function size(): int
    {
        return 3;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_proposta_comercial';
    }

    public static function sourceModel(): string
    {
        return PropostaComercial::class;
    }

    public function data(array $config): array
    {
        $abertas = PropostaComercial::query()->whereIn('status', [PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL, PropostaComercial::STATUS_APROVADA_INTERNA]);
        $n = (clone $abertas)->count();
        $valor = (float) (clone $abertas)->sum('total_value');

        $respondidas = PropostaComercial::query()->where('created_at', '>=', now()->subDays(180))
            ->whereIn('status', [PropostaComercial::STATUS_ACEITA_PELO_CLIENTE, PropostaComercial::STATUS_RECUSADA_PELO_CLIENTE])
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $aceitas = (int) ($respondidas[PropostaComercial::STATUS_ACEITA_PELO_CLIENTE] ?? 0);
        $taxa = $respondidas->sum() > 0 ? (int) round($aceitas / $respondidas->sum() * 100) : 0;

        return Payload::kpi($n, $n === 1 ? 'proposta' : 'propostas', $this->compacto($valor).' em jogo · aceite de '.$taxa.'% (6 meses)', 'n', ['pct' => $taxa, 'tone' => $taxa >= 50 ? 'ok' : 'warn']);
    }
}

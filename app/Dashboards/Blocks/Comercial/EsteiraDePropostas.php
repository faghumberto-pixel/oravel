<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\PropostaComercial;

class EsteiraDePropostas extends ComercialBlock
{
    use ComercialIaHelpers;

    public static function key(): string
    {
        return 'comercial.esteira_propostas';
    }

    public static function title(): string
    {
        return 'Esteira de propostas';
    }

    public static function subtitle(): string
    {
        return 'De rascunho até aceite do cliente (6 meses), com a conversão entre etapas';
    }

    public static function size(): int
    {
        return 6;
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
        $c = PropostaComercial::query()->where('created_at', '>=', now()->subDays(180))->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $n = fn (string ...$status) => (int) collect($status)->sum(fn ($s) => $c[$s] ?? 0);

        $criadas = (int) $c->sum();
        $enviadas = $criadas - $n(PropostaComercial::STATUS_RASCUNHO);
        $aprovadas = $n(PropostaComercial::STATUS_APROVADA_INTERNA, PropostaComercial::STATUS_ACEITA_PELO_CLIENTE, PropostaComercial::STATUS_RECUSADA_PELO_CLIENTE);
        $aceitas = $n(PropostaComercial::STATUS_ACEITA_PELO_CLIENTE);

        return Payload::funnel([['Criadas', $criadas], ['Enviadas para revisão', $enviadas], ['Aprovadas e enviadas ao cliente', $aprovadas], ['Aceitas pelo cliente', $aceitas]]);
    }
}

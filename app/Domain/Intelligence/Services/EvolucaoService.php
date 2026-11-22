<?php

namespace App\Domain\Intelligence\Services;

use App\Domain\Intelligence\Models\IntelligenceSnapshot;
use App\Domain\Intelligence\Support\Catalogo;
use Illuminate\Support\Collection;

/**
 * Lê o histórico de snapshots de uma empresa e responde "como evoluí?": último snapshot, comparação com o anterior e série por
 * indicador e por área. Só lê indicadores e diagnósticos já calculados (nunca dado operacional): é também a porta de leitura da futura IA.
 */
class EvolucaoService
{
    /**
     * @return ?array{
     *   atual: array{data: string, geral: ?array{nota: float, faixa: string, delta: ?float}},
     *   anterior: ?string,
     *   total_snapshots: int,
     *   areas: list<array{chave: string, rotulo: string, nota: float, faixa: string, delta: ?float, serie: list<float>, serie_alinhada: list<?float>}>,
     *   indicadores: list<array{nota: ?float, area: string, chave: string, titulo: string, unidade: string, valor: float, anterior: ?float, delta: ?float, sentido: ?string, serie: list<float>}>,
     *   destaques: list<string>,
     *   historico: list<array{data: string, geral: ?float, dados: array<string, mixed>}>
     * }
     */
    public function resumo(string $tenantId, int $limite = 12): ?array
    {
        /** @var Collection<int, IntelligenceSnapshot> $snaps */
        $snaps = IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->with(['indicators', 'diagnostics'])
            ->orderByDesc('data_referencia')->orderByDesc('created_at')->limit($limite)->get()->reverse()->values();

        if ($snaps->isEmpty()) {
            return null;
        }

        $atual = $snaps->last();
        $anterior = $snaps->count() > 1 ? $snaps[$snaps->count() - 2] : null;
        $titulos = Catalogo::indicadores();

        $notaDe = fn (?IntelligenceSnapshot $s, string $area): ?float => ($d = $s?->diagnostics->firstWhere('area', $area)) ? (float) $d->pontuacao : null;

        $areas = [];
        foreach (config('intelligence.areas') as $chave => $rotulo) {
            $d = $atual->diagnostics->firstWhere('area', $chave);
            if (! $d) {
                continue;
            }
            $antes = $notaDe($anterior, $chave);
            $areas[] = [
                'chave' => $chave, 'rotulo' => $rotulo, 'nota' => (float) $d->pontuacao, 'faixa' => (string) $d->faixa,
                'delta' => $antes === null ? null : round((float) $d->pontuacao - $antes, 2),
                'serie' => $snaps->map(fn ($s) => $notaDe($s, $chave))->filter(fn ($v) => $v !== null)->values()->all(),
                // Uma posição por snapshot (null = área sem nota naquela data): é o que os gráficos de linha precisam.
                'serie_alinhada' => $snaps->map(fn ($s) => $notaDe($s, $chave))->all(),
            ];
        }

        $indicadores = [];
        foreach ($atual->indicators as $i) {
            $antes = $anterior?->indicators->firstWhere('chave', $i->chave);
            $delta = $antes ? round((float) $i->valor - (float) $antes->valor, 4) : null;
            $ruim = $i->meta['ruim'] ?? null;
            $bom = $i->meta['bom'] ?? null;
            $sentido = null;
            if ($delta !== null && $ruim !== null && $bom !== null) {
                $sentido = $delta == 0 ? 'igual' : (($delta > 0) === ($bom > $ruim) ? 'melhor' : 'pior');
            }
            $nota = ($ruim !== null && $bom !== null && $bom != $ruim)
                ? round(max(0, min(1, ((float) $i->valor - $ruim) / ($bom - $ruim))) * 100, 1) : null;
            $indicadores[] = [
                'nota' => $nota,
                'area' => $i->meta['area'] ?? 'geral', 'chave' => $i->chave, 'titulo' => $titulos[$i->chave]['titulo'] ?? $i->chave,
                'unidade' => (string) $i->unidade, 'valor' => (float) $i->valor, 'anterior' => $antes ? (float) $antes->valor : null,
                'delta' => $delta, 'sentido' => $sentido,
                'serie' => $snaps->map(fn ($s) => ($x = $s->indicators->firstWhere('chave', $i->chave)) ? (float) $x->valor : null)->filter(fn ($v) => $v !== null)->values()->all(),
            ];
        }

        // Frases de evolução: os indicadores com nota que mais mexeram, em palavras (a pergunta "melhorei?").
        $destaques = collect($indicadores)->filter(fn ($i) => in_array($i['sentido'], ['melhor', 'pior'], true))
            ->sortByDesc(fn ($i) => abs($i['delta'] / max(abs($i['anterior']), 1)))->take(5)
            ->map(fn ($i) => sprintf(
                '%s %s: de %s para %s %s.',
                $i['titulo'], $i['sentido'] === 'melhor' ? 'melhorou' : 'piorou',
                $this->num($i['anterior']), $this->num($i['valor']), $i['unidade'],
            ))->values()->all();

        $geral = $atual->diagnostics->firstWhere('area', 'geral');
        $geralAntes = $notaDe($anterior, 'geral');

        return [
            'atual' => [
                'data' => $atual->data_referencia->toDateString(),
                'geral' => $geral ? ['nota' => (float) $geral->pontuacao, 'faixa' => (string) $geral->faixa, 'delta' => $geralAntes === null ? null : round((float) $geral->pontuacao - $geralAntes, 2)] : null,
            ],
            'anterior' => $anterior?->data_referencia->toDateString(),
            'total_snapshots' => IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $tenantId)->count(),
            'areas' => $areas,
            'indicadores' => $indicadores,
            'destaques' => $destaques,
            'historico' => $snaps->map(fn ($s) => ['data' => $s->data_referencia->toDateString(), 'geral' => $notaDe($s, 'geral'), 'dados' => (array) $s->dados])->all(),
        ];
    }

    /** @return array<string, string> id do snapshot => data (mais recente primeiro), para escolher o que comparar. */
    public function opcoes(string $tenantId): array
    {
        return IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->orderByDesc('data_referencia')->orderByDesc('created_at')->get(['id', 'data_referencia'])
            ->mapWithKeys(fn ($s) => [$s->id => $s->data_referencia->format('d/m/Y')])->all();
    }

    /**
     * Comparação interna: a empresa contra ela mesma em duas fotografias (qualquer par). Devolve null se algum id não for da empresa.
     *
     * @return ?array{de: string, para: string, areas: list<array>, indicadores: list<array>}
     */
    public function comparar(string $tenantId, string $deId, string $paraId): ?array
    {
        $snaps = IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', [$deId, $paraId])->with(['indicators', 'diagnostics'])->get()->keyBy('id');
        $de = $snaps->get($deId);
        $para = $snaps->get($paraId);
        if (! $de || ! $para) {
            return null;
        }

        $nota = fn (IntelligenceSnapshot $s, string $a) => ($d = $s->diagnostics->firstWhere('area', $a)) ? (float) $d->pontuacao : null;
        $areas = [];
        foreach (config('intelligence.areas') as $chave => $rotulo) {
            $a = $nota($de, $chave);
            $b = $nota($para, $chave);
            if ($a === null && $b === null) {
                continue;
            }
            $areas[] = ['chave' => $chave, 'rotulo' => $rotulo, 'de' => $a, 'para' => $b, 'delta' => ($a !== null && $b !== null) ? round($b - $a, 2) : null];
        }

        $titulos = Catalogo::indicadores();
        $indicadores = [];
        foreach ($para->indicators as $i) {
            $antes = $de->indicators->firstWhere('chave', $i->chave);
            $ruim = $i->meta['ruim'] ?? null;
            $bom = $i->meta['bom'] ?? null;
            $delta = $antes ? round((float) $i->valor - (float) $antes->valor, 4) : null;
            $sentido = null;
            if ($delta !== null && $ruim !== null && $bom !== null) {
                $sentido = $delta == 0 ? 'igual' : (($delta > 0) === ($bom > $ruim) ? 'melhor' : 'pior');
            }
            $indicadores[] = ['area' => $i->meta['area'] ?? 'geral', 'chave' => $i->chave, 'titulo' => $titulos[$i->chave]['titulo'] ?? $i->chave, 'unidade' => (string) $i->unidade,
                'de' => $antes ? (float) $antes->valor : null, 'para' => (float) $i->valor, 'delta' => $delta, 'sentido' => $sentido];
        }

        return ['de' => $de->data_referencia->toDateString(), 'para' => $para->data_referencia->toDateString(), 'areas' => $areas, 'indicadores' => $indicadores];
    }

    private function num(float $v): string
    {
        return number_format($v, abs($v) >= 100 ? 0 : 1, ',', '.');
    }
}

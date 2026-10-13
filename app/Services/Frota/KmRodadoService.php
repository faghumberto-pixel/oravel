<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaPercurso;
use App\Models\FrotaSaidaVeiculo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Km rodado da frota.
 *  - TOTAL do veículo: histórico de leituras de odômetro (todo registro de km do sistema grava uma leitura): soma só os avanços;
 *    queda (painel trocado, com justificativa) não desconta.
 *  - Km das VIAGENS (trechos): odômetro na entrada − odômetro na saída; vai para o percurso do trecho (nos dois sentidos).
 *  - "Fora de percurso" = total − km das viagens com percurso (abastecer, mover no pátio, trechos sem percurso cadastrado).
 */
class KmRodadoService
{
    /** @return array<string, string> chave => rótulo dos períodos oferecidos nas telas e no relatório */
    public static function periodos(): array
    {
        return ['mes' => 'Mês atual', '30d' => 'Últimos 30 dias', '90d' => 'Últimos 90 dias', '12m' => 'Últimos 12 meses', 'tudo' => 'Desde o início'];
    }

    /** @return array{0: ?Carbon, 1: ?Carbon, 2: string} [de, ate, rótulo] */
    public static function periodo(string $chave): array
    {
        $chave = array_key_exists($chave, self::periodos()) ? $chave : 'mes';
        $de = match ($chave) {
            'mes' => now()->startOfMonth(),
            '30d' => now()->subDays(30)->startOfDay(),
            '90d' => now()->subDays(90)->startOfDay(),
            '12m' => now()->subMonths(12)->startOfDay(),
            default => null,
        };

        return [$de, $chave === 'tudo' ? null : now(), self::periodos()[$chave]];
    }

    /** Km real de uma viagem concluída (nunca negativo). */
    private const SQL_KM_VIAGEM = 'GREATEST(odometro_retorno - odometro_saida, 0)';

    /**
     * @return array{total: int, em_viagens: int, em_percursos: int, fora_de_percurso: int, viagens: int, custo: ?float, por_percurso: Collection<int, array{percurso: ?FrotaPercurso, nome: string, viagens: int, km: int}>}
     */
    public function porVeiculo(Asset $ativo, ?Carbon $de = null, ?Carbon $ate = null): array
    {
        $total = $this->totalRodado($ativo->id, $de, $ate);

        $viagens = $this->viagensConcluidas($de, $ate)->where('ativo_id', $ativo->id)
            ->selectRaw('percurso_id, COUNT(*) as viagens, COALESCE(SUM('.self::SQL_KM_VIAGEM.'), 0) as km')->groupBy('percurso_id')->get();

        $percursos = FrotaPercurso::query()->whereIn('id', $viagens->pluck('percurso_id')->filter())->get()->keyBy('id');
        $porPercurso = $viagens->map(fn ($v) => [
            'percurso' => $percursos->get($v->percurso_id), 'nome' => $percursos->get($v->percurso_id)?->nome ?? 'Sem percurso',
            'viagens' => (int) $v->viagens, 'km' => (int) $v->km,
        ])->sortByDesc('km')->values();

        $emViagens = (int) $porPercurso->sum('km');
        $emPercursos = (int) $porPercurso->filter(fn ($p) => $p['percurso'])->sum('km');
        $custoKm = (float) $ativo->cost_per_km;

        return [
            'total' => $total,
            'em_viagens' => $emViagens,
            'em_percursos' => $emPercursos,
            'fora_de_percurso' => max(0, $total - $emPercursos),
            'viagens' => (int) $porPercurso->sum('viagens'),
            'custo' => $custoKm > 0 ? round($total * $custoKm, 2) : null,
            'por_percurso' => $porPercurso,
        ];
    }

    /**
     * Cada percurso com as viagens e o km que recebeu (nos dois sentidos).
     *
     * @return Collection<int, array{percurso: FrotaPercurso, viagens: int, km_total: int, km_medio: ?float, desvio_pct: ?float}>
     */
    public function porPercurso(?Carbon $de = null, ?Carbon $ate = null): Collection
    {
        $soma = $this->viagensConcluidas($de, $ate)->whereNotNull('percurso_id')
            ->selectRaw('percurso_id, COUNT(*) as viagens, COALESCE(SUM('.self::SQL_KM_VIAGEM.'), 0) as km')->groupBy('percurso_id')->get()->keyBy('percurso_id');

        return FrotaPercurso::query()->orderBy('nome')->get()->map(function (FrotaPercurso $p) use ($soma) {
            $s = $soma->get($p->id);
            $viagens = (int) ($s->viagens ?? 0);
            $km = (int) ($s->km ?? 0);
            $medio = $viagens > 0 ? round($km / $viagens, 1) : null;
            $previsto = (float) $p->km_previsto;

            return [
                'percurso' => $p, 'viagens' => $viagens, 'km_total' => $km, 'km_medio' => $medio,
                'desvio_pct' => $medio !== null && $previsto > 0 ? round(($medio - $previsto) / $previsto * 100, 1) : null,
            ];
        });
    }

    /**
     * Todos os veículos com o total rodado e o km das viagens no período.
     *
     * @return Collection<int, array{ativo: Asset, total: int, em_percursos: int, fora_de_percurso: int, viagens: int, custo: ?float}>
     */
    public function frota(?Carbon $de = null, ?Carbon $ate = null): Collection
    {
        return Asset::query()->where('grupo', Asset::GRUPO_VEICULO)->naoBaixado()->orderBy('placa')->get()->map(function (Asset $a) use ($de, $ate) {
            $r = $this->porVeiculo($a, $de, $ate);

            return ['ativo' => $a, 'total' => $r['total'], 'em_percursos' => $r['em_percursos'], 'fora_de_percurso' => $r['fora_de_percurso'], 'viagens' => $r['viagens'], 'custo' => $r['custo']];
        })->sortByDesc('total')->values();
    }

    private function viagensConcluidas(?Carbon $de, ?Carbon $ate)
    {
        return FrotaSaidaVeiculo::query()->whereNotNull('retorno_em')->whereNotNull('odometro_retorno')
            ->when($de, fn ($q) => $q->where('retorno_em', '>=', $de))
            ->when($ate, fn ($q) => $q->where('retorno_em', '<=', $ate));
    }

    private function totalRodado(string $ativoId, ?Carbon $de, ?Carbon $ate): int
    {
        $base = FrotaLeituraOdometro::query()->where('ativo_id', $ativoId)->orderBy('lido_em')->orderBy('created_at');

        $antes = $de ? (clone $base)->where('lido_em', '<', $de)->get()->last() : null;
        $dentro = (clone $base)->when($de, fn ($q) => $q->where('lido_em', '>=', $de))->when($ate, fn ($q) => $q->where('lido_em', '<=', $ate))->get();

        $odometros = collect([$antes])->filter()->merge($dentro)->pluck('odometro')->values();
        $total = 0;
        for ($i = 1; $i < $odometros->count(); $i++) {
            $total += max(0, $odometros[$i] - $odometros[$i - 1]);
        }

        return $total;
    }
}

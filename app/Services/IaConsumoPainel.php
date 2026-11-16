<?php

namespace App\Services;

use App\Support\Ia\ConsumoIa;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Numeros do painel "Consumo de IA" da Central (so super admin): tokens e custo estimado por periodo,
 * aplicativo, cliente e usuario, a partir de ia_consumos. Os filtros valem pra tudo que o painel mostra.
 */
class IaConsumoPainel
{
    public const PERIODOS = [
        'hora' => 'Última hora',
        'hoje' => 'Hoje',
        '24h' => 'Últimas 24 horas',
        '7d' => 'Últimos 7 dias',
        '30d' => 'Últimos 30 dias',
        'mes' => 'Este mês',
        'tudo' => 'Todo o período',
    ];

    public function __construct(
        private readonly string $periodo = 'hoje',
        private readonly ?string $area = null,
        private readonly ?string $tenantId = null,
        private readonly ?string $userId = null,
    ) {}

    public static function desde(string $periodo): ?Carbon
    {
        return match ($periodo) {
            'hora' => now()->subHour(),
            'hoje' => now()->startOfDay(),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            'mes' => now()->startOfMonth(),
            default => null,
        };
    }

    public static function dolar(): float
    {
        return (float) config('ia.dolar', 5.56);
    }

    private function base(bool $comPeriodo = true): Builder
    {
        $q = DB::table('ia_consumos');

        if ($comPeriodo && ($desde = self::desde($this->periodo))) {
            $q->where('ia_consumos.created_at', '>=', $desde);
        }

        return $q
            ->when($this->area, fn ($q, $a) => $q->where('ia_consumos.area', $a))
            ->when($this->tenantId, fn ($q, $t) => $q->where('ia_consumos.tenant_id', $t))
            ->when($this->userId, fn ($q, $u) => $q->where('ia_consumos.user_id', $u));
    }

    private const SOMAS = 'COUNT(*) AS chamadas, COALESCE(SUM(input_tokens),0) AS entrada, COALESCE(SUM(output_tokens),0) AS saida, COALESCE(SUM(cache_read_tokens),0) AS cache_leitura, COALESCE(SUM(custo_usd),0) AS custo_usd';

    /** @return array<string, int|float> */
    public function resumo(bool $comPeriodo = true): array
    {
        $r = $this->base($comPeriodo)->selectRaw(self::SOMAS.", COALESCE(SUM(CASE WHEN etapa = 'retry' THEN 1 ELSE 0 END),0) AS retries")->first();

        return $this->normalizar($r) + ['retries' => (int) $r->retries];
    }

    /** @return array<string, int|float> */
    private function normalizar(object $r): array
    {
        $dolar = self::dolar();

        return [
            'chamadas' => (int) $r->chamadas,
            'entrada' => (int) $r->entrada,
            'saida' => (int) $r->saida,
            'cache_leitura' => (int) $r->cache_leitura,
            'tokens' => (int) $r->entrada + (int) $r->saida,
            'custo_usd' => (float) $r->custo_usd,
            'custo_brl' => (float) $r->custo_usd * $dolar,
        ];
    }

    /**
     * Barras do tempo (minuto / hora / dia conforme o periodo), com os intervalos vazios preenchidos com zero.
     *
     * @return array{rotulos: list<string>, entrada: list<int>, saida: list<int>, passo: string}
     */
    public function serie(): array
    {
        $passo = match ($this->periodo) {
            'hora' => 'minute',
            'hoje', '24h' => 'hour',
            default => 'day',
        };

        $linhas = $this->base()
            ->selectRaw("date_trunc('{$passo}', ia_consumos.created_at) AS b, SUM(input_tokens) AS entrada, SUM(output_tokens) AS saida")
            ->groupBy('b')->orderBy('b')->get()
            ->mapWithKeys(fn ($l) => [Carbon::parse($l->b)->format('Y-m-d H:i') => $l]);

        $inicio = self::desde($this->periodo) ?? ($linhas->isNotEmpty() ? Carbon::parse($linhas->keys()->first()) : now()->subDays(6));
        $inicio = $inicio->copy()->startOf($passo);
        $fim = now()->startOf($passo);

        $rotulos = $entrada = $saida = [];
        for ($t = $inicio->copy(); $t <= $fim; $t = $t->copy()->add(1, $passo)) {
            $l = $linhas->get($t->format('Y-m-d H:i'));
            $rotulos[] = match ($passo) {
                'minute' => $t->format('H:i'),
                'hour' => $t->format('d/m H\h'),
                default => $t->format('d/m'),
            };
            $entrada[] = (int) ($l->entrada ?? 0);
            $saida[] = (int) ($l->saida ?? 0);

            if (count($rotulos) > 400) { // protecao: periodo "tudo" muito longo
                break;
            }
        }

        return ['rotulos' => $rotulos, 'entrada' => $entrada, 'saida' => $saida, 'passo' => $passo];
    }

    /**
     * Uma linha por aplicativo conhecido (mesmo sem uso, pra mostrar o que ainda nao foi ligado) + os que tiverem uso.
     *
     * @return list<array<string, mixed>>
     */
    public function porArea(): array
    {
        $dolar = self::dolar();
        $dados = $this->base()->selectRaw('area, '.self::SOMAS)->groupBy('area')->get()->keyBy('area');
        $total = max(1, (int) $dados->sum(fn ($d) => $d->entrada + $d->saida));

        $chaves = array_values(array_unique([...array_keys(ConsumoIa::AREAS), ...$dados->keys()->all()]));

        return array_map(function (string $area) use ($dados, $total, $dolar) {
            $d = $dados->get($area);
            $tokens = $d ? (int) $d->entrada + (int) $d->saida : 0;

            return [
                'area' => $area,
                'rotulo' => ConsumoIa::rotulo($area),
                'chamadas' => (int) ($d->chamadas ?? 0),
                'tokens' => $tokens,
                'custo_brl' => (float) ($d->custo_usd ?? 0) * $dolar,
                'percentual' => round($tokens / $total * 100, 1),
            ];
        }, $chaves);
    }

    /** @return list<array<string, mixed>> */
    public function porCliente(int $limite = 15): array
    {
        $dolar = self::dolar();
        $linhas = $this->base()
            ->leftJoin('tenants', 'tenants.id', '=', 'ia_consumos.tenant_id')
            ->selectRaw('ia_consumos.tenant_id AS chave, MAX(tenants.name) AS nome, '.self::SOMAS)
            ->groupBy('ia_consumos.tenant_id')
            ->orderByRaw('SUM(input_tokens + output_tokens) DESC')->limit($limite)->get();

        return $this->rankear($linhas, 'Sem cliente (plataforma)', $dolar);
    }

    /** @return list<array<string, mixed>> */
    public function porUsuario(int $limite = 15): array
    {
        $dolar = self::dolar();
        $linhas = $this->base()
            ->leftJoin('users', 'users.id', '=', 'ia_consumos.user_id')
            ->selectRaw('ia_consumos.user_id AS chave, MAX(users.name) AS nome, MAX(users.email) AS email, '.self::SOMAS)
            ->groupBy('ia_consumos.user_id')
            ->orderByRaw('SUM(input_tokens + output_tokens) DESC')->limit($limite)->get();

        return $this->rankear($linhas, 'Sistema (sem usuário)', $dolar);
    }

    /**
     * @param  iterable<object>  $linhas
     * @return list<array<string, mixed>>
     */
    private function rankear(iterable $linhas, string $semNome, float $dolar): array
    {
        $linhas = collect($linhas);
        $max = max(1, (int) $linhas->max(fn ($l) => $l->entrada + $l->saida));

        return $linhas->map(fn ($l) => [
            'chave' => $l->chave,
            'nome' => $l->nome ?: ($l->chave ? 'Removido ('.substr((string) $l->chave, 0, 8).')' : $semNome),
            'email' => $l->email ?? null,
            'chamadas' => (int) $l->chamadas,
            'tokens' => (int) $l->entrada + (int) $l->saida,
            'custo_brl' => (float) $l->custo_usd * $dolar,
            'barra' => round(((int) $l->entrada + (int) $l->saida) / $max * 100, 1),
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    public function ultimas(int $limite = 15): array
    {
        $dolar = self::dolar();

        return $this->base(false)
            ->leftJoin('tenants', 'tenants.id', '=', 'ia_consumos.tenant_id')
            ->leftJoin('users', 'users.id', '=', 'ia_consumos.user_id')
            ->orderByDesc('ia_consumos.created_at')->limit($limite)
            ->get(['ia_consumos.created_at', 'ia_consumos.area', 'ia_consumos.etapa', 'ia_consumos.model', 'ia_consumos.input_tokens', 'ia_consumos.output_tokens', 'ia_consumos.custo_usd', 'tenants.name AS cliente', 'users.name AS usuario'])
            ->map(fn ($l) => [
                'quando' => Carbon::parse($l->created_at),
                'area' => ConsumoIa::rotulo($l->area),
                'etapa' => $l->etapa,
                'modelo' => $l->model,
                'entrada' => (int) $l->input_tokens,
                'saida' => (int) $l->output_tokens,
                'custo_brl' => (float) $l->custo_usd * $dolar,
                'cliente' => $l->cliente ?: '—',
                'usuario' => $l->usuario ?: '—',
            ])->all();
    }

    /** Opcoes dos filtros: so quem ja teve consumo. @return array<string, string> */
    public static function clientesComConsumo(): array
    {
        return DB::table('ia_consumos')->join('tenants', 'tenants.id', '=', 'ia_consumos.tenant_id')
            ->distinct()->orderBy('tenants.name')->pluck('tenants.name', 'tenants.id')->all();
    }

    /** @return array<string, string> */
    public static function usuariosComConsumo(): array
    {
        return DB::table('ia_consumos')->join('users', 'users.id', '=', 'ia_consumos.user_id')
            ->distinct()->orderBy('users.name')->get(['users.id', 'users.name', 'users.email'])
            ->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->email.')'])->all();
    }

    public static function primeiroRegistro(): ?Carbon
    {
        $v = DB::table('ia_consumos')->min('created_at');

        return $v ? Carbon::parse($v) : null;
    }
}

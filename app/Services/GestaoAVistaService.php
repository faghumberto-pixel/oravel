<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetDowntimeEvent;
use App\Models\MaintenanceOrder;
use App\Models\Tenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

/**
 * Centraliza os calculos do dashboard "Gestao a Vista" (aba do Painel de
 * Controle, ver App\Filament\Pages\PainelGestao). Cada metodo publico
 * corresponde a um bloco do dashboard e recebe os mesmos 4 filtros
 * (from/until/branchId/assetId) -- devolvidos como array associativo cru,
 * sem HTML/formatacao alem de valores monetarios (BRL) ja resolvidos por
 * Number::currency(), pra widgets ficarem finos e testaveis.
 *
 * Janela de retrabalho (efetividade) fixa em 30 dias -- mesmo criterio ja
 * usado por CorrectiveReworkAnalysisService::buildContext() (mesmo ativo +
 * nova Corretiva dentro de N dias da conclusao anterior), invertido: SEM
 * retrabalho = efetivo.
 */
class GestaoAVistaService
{
    private const RETRABALHO_JANELA_DIAS = 30;

    private const STATUS_CONCLUIDA = ['Concluída', 'Completado'];

    public function __construct(private readonly string $tenantId)
    {
    }

    /**
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     */
    private function baseQuery(array $filtros)
    {
        return MaintenanceOrder::query()
            ->where('tenant_id', $this->tenantId)
            ->when($filtros['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filtros['until'] ?? null, fn ($q, $until) => $q->whereDate('created_at', '<=', $until))
            ->when($filtros['branchId'] ?? null, fn ($q, $branchId) => $q->where('branch_id', $branchId))
            ->when($filtros['assetId'] ?? null, fn ($q, $assetId) => $q->where('asset_id', $assetId));
    }

    /**
     * Periodo imediatamente anterior, de mesmo tamanho em dias, usado nas
     * comparacoes de variacao (%) e p.p. de todos os KPIs. Sem from/until
     * informados, nao ha "periodo anterior" comparavel -- devolve null.
     *
     * @param  array{from: ?string, until: ?string}  $filtros
     * @return array{from: string, until: string}|null
     */
    private function periodoAnterior(array $filtros): ?array
    {
        if (empty($filtros['from']) || empty($filtros['until'])) {
            return null;
        }

        $from = Carbon::parse($filtros['from'])->startOfDay();
        $until = Carbon::parse($filtros['until'])->endOfDay();
        $dias = $from->diffInDays($until) + 1;

        return [
            'from' => $from->copy()->subDays($dias)->toDateString(),
            'until' => $from->copy()->subDay()->toDateString(),
        ];
    }

    /**
     * Resumo de OS no periodo: Planejadas (todas as OS abertas no
     * periodo), Concluidas (status finalizado) e Atrasadas (SLA estourado
     * e ainda nao concluida) -- mesmo criterio de sla_target_minutes ja
     * usado por MaintenanceOrder::slaColor(), aqui contado a partir de
     * created_at.
     *
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     * @return array{planejadas: int, concluidas: int, atrasadas: int}
     */
    public function resumoOs(array $filtros): array
    {
        $planejadas = (clone $this->baseQuery($filtros))
            ->where('maintenance_type', '!=', MaintenanceOrder::TYPE_RESERVA)
            ->count();

        $concluidas = (clone $this->baseQuery($filtros))
            ->where('maintenance_type', '!=', MaintenanceOrder::TYPE_RESERVA)
            ->whereIn('status', self::STATUS_CONCLUIDA)
            ->count();

        $atrasadas = (clone $this->baseQuery($filtros))
            ->where('maintenance_type', '!=', MaintenanceOrder::TYPE_RESERVA)
            ->whereNotIn('status', self::STATUS_CONCLUIDA)
            ->whereNotNull('sla_target_minutes')
            ->whereRaw('created_at + (sla_target_minutes || \' minutes\')::interval < ?', [now()])
            ->count();

        return [
            'planejadas' => $planejadas,
            'concluidas' => $concluidas,
            'atrasadas' => $atrasadas,
        ];
    }

    /**
     * Distribuicao % de OS finalizadas por maintenance_type. O sistema nao
     * tem tipo "Preditiva" -- mostra as categorias reais existentes
     * (Preventiva/Corretiva, mais qualquer outra que aparecer no periodo),
     * nao um vocabulario fixo de 3 categorias genericas.
     *
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     * @return array<int, array{tipo: string, quantidade: int, percentual: float}>
     */
    public function distribuicaoPorTipo(array $filtros): array
    {
        $porTipo = (clone $this->baseQuery($filtros))
            ->whereIn('status', self::STATUS_CONCLUIDA)
            ->selectRaw('maintenance_type, count(*) as total')
            ->groupBy('maintenance_type')
            ->pluck('total', 'maintenance_type');

        $totalGeral = $porTipo->sum();

        if ($totalGeral === 0) {
            return [];
        }

        return $porTipo->map(fn ($total, $tipo) => [
            'tipo' => $tipo,
            'quantidade' => $total,
            'percentual' => round(($total / $totalGeral) * 100, 1),
        ])->values()->all();
    }

    /**
     * Serie mensal de OS finalizadas por tipo, separando Preventiva e
     * Corretiva (as duas categorias sempre relevantes pro grafico de area
     * empilhada) -- qualquer outro maintenance_type concluido no periodo
     * (Avaria/Troca/Emergencia etc, mais raros) entra agregado em
     * "Outras", pra nao poluir o grafico com series demais.
     *
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     * @return array{labels: array<int,string>, preventiva: array<int,int>, corretiva: array<int,int>, outras: array<int,int>}
     */
    public function distribuicaoPorTipoMensal(array $filtros): array
    {
        $labels = [];
        $preventiva = [];
        $corretiva = [];
        $outras = [];

        foreach ($this->mesesDoPeriodo($filtros) as $mes) {
            $filtrosDoMes = array_merge($filtros, [
                'from' => $mes->toDateString(),
                'until' => $mes->copy()->endOfMonth()->toDateString(),
            ]);

            $porTipo = (clone $this->baseQuery($filtrosDoMes))
                ->whereIn('status', self::STATUS_CONCLUIDA)
                ->selectRaw('maintenance_type, count(*) as total')
                ->groupBy('maintenance_type')
                ->pluck('total', 'maintenance_type');

            $labels[] = ucfirst($mes->translatedFormat('M/y'));
            $preventiva[] = (int) ($porTipo[MaintenanceOrder::TYPE_PREVENTIVE] ?? 0);
            $corretiva[] = (int) ($porTipo[MaintenanceOrder::TYPE_CORRECTIVE] ?? 0);
            $outras[] = (int) $porTipo->except([MaintenanceOrder::TYPE_PREVENTIVE, MaintenanceOrder::TYPE_CORRECTIVE])->sum();
        }

        return [
            'labels' => $labels,
            'preventiva' => $preventiva,
            'corretiva' => $corretiva,
            'outras' => $outras,
        ];
    }

    /**
     * Custo total de manutencao no periodo (labor+material+logistics, ja
     * somados em total_order_cost) e variacao % vs. periodo anterior de
     * mesmo tamanho.
     *
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     * @return array{valor: float, valor_formatado: string, variacao_percentual: ?float}
     */
    public function custoTotal(array $filtros): array
    {
        $valorAtual = (float) (clone $this->baseQuery($filtros))->sum('total_order_cost');

        $anterior = $this->periodoAnterior($filtros);
        $variacao = null;

        if ($anterior) {
            $valorAnterior = (float) $this->baseQuery(array_merge($filtros, $anterior))->sum('total_order_cost');
            if ($valorAnterior > 0) {
                $variacao = round((($valorAtual - $valorAnterior) / $valorAnterior) * 100, 1);
            }
        }

        return [
            'valor' => $valorAtual,
            'valor_formatado' => Number::currency($valorAtual, in: 'BRL'),
            'variacao_percentual' => $variacao,
        ];
    }

    /**
     * Lista de meses (1o dia de cada mes) cobertos pelo periodo filtrado,
     * usada pelas series mensais dos graficos de evolucao. Sem from/until,
     * usa os ultimos 6 meses corridos (mesmo tamanho de janela ja usado
     * por MaintenanceCostChart::mount(), soh que com 6 em vez de 5 pra
     * dar um ponto extra de contexto nas series novas).
     *
     * @param  array{from: ?string, until: ?string}  $filtros
     * @return Collection<int, CarbonInterface>
     */
    private function mesesDoPeriodo(array $filtros): Collection
    {
        $from = ! empty($filtros['from'])
            ? Carbon::parse($filtros['from'])->startOfMonth()
            : now()->subMonths(5)->startOfMonth();
        $until = ! empty($filtros['until'])
            ? Carbon::parse($filtros['until'])->startOfMonth()
            : now()->startOfMonth();

        $meses = collect();
        $cursor = $from->copy();
        while ($cursor->lessThanOrEqualTo($until)) {
            $meses->push($cursor->copy());
            $cursor->addMonth();
        }

        return $meses;
    }

    /**
     * % de OS Concluidas em relacao as Planejadas no periodo, variacao em
     * p.p. vs. periodo anterior, e serie mensal Planejado vs Realizado
     * (contagem de OS criadas x concluidas por mes, dentro do periodo).
     *
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     * @return array{percentual: ?float, variacao_pp: ?float, serie_mensal: array{labels: array<int,string>, planejado: array<int,int>, realizado: array<int,int>}}
     */
    public function percentualManutencaoRealizada(array $filtros): array
    {
        $resumo = $this->resumoOs($filtros);
        $percentual = $resumo['planejadas'] > 0
            ? round(($resumo['concluidas'] / $resumo['planejadas']) * 100, 1)
            : null;

        $variacaoPp = null;
        $anterior = $this->periodoAnterior($filtros);
        if ($anterior && $percentual !== null) {
            $resumoAnterior = $this->resumoOs(array_merge($filtros, $anterior));
            if ($resumoAnterior['planejadas'] > 0) {
                $percentualAnterior = round(($resumoAnterior['concluidas'] / $resumoAnterior['planejadas']) * 100, 1);
                $variacaoPp = round($percentual - $percentualAnterior, 1);
            }
        }

        $labels = [];
        $planejado = [];
        $realizado = [];

        foreach ($this->mesesDoPeriodo($filtros) as $mes) {
            $filtrosDoMes = array_merge($filtros, [
                'from' => $mes->toDateString(),
                'until' => $mes->copy()->endOfMonth()->toDateString(),
            ]);
            $resumoDoMes = $this->resumoOs($filtrosDoMes);

            $labels[] = ucfirst($mes->translatedFormat('M/y'));
            $planejado[] = $resumoDoMes['planejadas'];
            $realizado[] = $resumoDoMes['concluidas'];
        }

        return [
            'percentual' => $percentual,
            'variacao_pp' => $variacaoPp,
            'serie_mensal' => [
                'labels' => $labels,
                'planejado' => $planejado,
                'realizado' => $realizado,
            ],
        ];
    }

    /**
     * IDs de ativos do tenant relevantes ao filtro -- se assetId
     * informado, so' ele; senao, todos os ativos do tenant (com filtro de
     * branch aplicado via internal_unit nao e' possivel hoje, ver ressalva
     * no plano: Asset usa internal_unit_id, nao branch_id, entao o filtro
     * de Unidade so' restringe OS, nao a base de ativos usada aqui).
     *
     * @param  array{assetId: ?string}  $filtros
     * @return Collection<int, string>
     */
    private function assetIdsRelevantes(array $filtros): Collection
    {
        if (! empty($filtros['assetId'])) {
            return collect([$filtros['assetId']]);
        }

        return Asset::query()->where('tenant_id', $this->tenantId)->pluck('id');
    }

    /**
     * % de disponibilidade da frota no periodo: (horas totais possiveis -
     * horas de parada nao planejada) / horas totais possiveis. "Horas
     * totais possiveis" = qtd de ativos relevantes x horas do periodo
     * (aproximacao -- nao desconta ativos criados/baixados no meio do
     * periodo, ressalva documentada no relatorio final). Parada nao
     * planejada = AssetDowntimeEvent com reason quebra/manutencao_corretiva
     * que se sobrepoe ao periodo.
     *
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     * @return array{percentual: ?float, variacao_pp: ?float, serie_mensal: array{labels: array<int,string>, valores: array<int,float>}}
     */
    public function disponibilidadeEquipamentos(array $filtros): array
    {
        $percentual = $this->calcularDisponibilidade($filtros);

        $variacaoPp = null;
        $anterior = $this->periodoAnterior($filtros);
        if ($anterior && $percentual !== null) {
            $percentualAnterior = $this->calcularDisponibilidade(array_merge($filtros, $anterior));
            if ($percentualAnterior !== null) {
                $variacaoPp = round($percentual - $percentualAnterior, 1);
            }
        }

        $labels = [];
        $valores = [];
        foreach ($this->mesesDoPeriodo($filtros) as $mes) {
            $filtrosDoMes = array_merge($filtros, [
                'from' => $mes->toDateString(),
                'until' => $mes->copy()->endOfMonth()->toDateString(),
            ]);
            $labels[] = ucfirst($mes->translatedFormat('M/y'));
            $valores[] = $this->calcularDisponibilidade($filtrosDoMes) ?? 0.0;
        }

        return [
            'percentual' => $percentual,
            'variacao_pp' => $variacaoPp,
            'serie_mensal' => ['labels' => $labels, 'valores' => $valores],
        ];
    }

    /**
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     */
    private function calcularDisponibilidade(array $filtros): ?float
    {
        $assetIds = $this->assetIdsRelevantes($filtros);
        if ($assetIds->isEmpty()) {
            return null;
        }

        $from = ! empty($filtros['from']) ? Carbon::parse($filtros['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $until = ! empty($filtros['until']) ? Carbon::parse($filtros['until'])->endOfDay() : now()->endOfDay();
        $horasPeriodo = $from->diffInHours($until, true);

        if ($horasPeriodo <= 0) {
            return null;
        }

        $horasTotaisPossiveis = $horasPeriodo * $assetIds->count();

        $minutosParada = AssetDowntimeEvent::query()
            ->where('tenant_id', $this->tenantId)
            ->whereIn('asset_id', $assetIds)
            ->whereIn('reason', [AssetDowntimeEvent::REASON_QUEBRA, AssetDowntimeEvent::REASON_MANUTENCAO_CORRETIVA])
            ->where('started_at', '<=', $until)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $from))
            ->get()
            ->sum(function (AssetDowntimeEvent $evento) use ($from, $until) {
                $inicio = $evento->started_at->max($from);
                // Evento ainda aberto (ended_at null) conta como parado ate agora, nunca
                // alem disso -- sem o min(now()) aqui, um evento aberto com $until no
                // futuro (mes corrente, por exemplo) contava parada "no futuro" que ainda
                // nem aconteceu, inflando o total de forma irreal.
                $fim = ($evento->ended_at ?? $until->copy()->min(now()))->min($until);

                return max(0, $inicio->diffInMinutes($fim, true));
            });

        $horasParada = $minutosParada / 60;

        return round((($horasTotaisPossiveis - $horasParada) / $horasTotaisPossiveis) * 100, 1);
    }

    /**
     * First Time Fix Rate: % de OS Corretivas concluidas no periodo que
     * NAO tiveram retrabalho -- mesmo criterio de
     * CorrectiveReworkAnalysisService::buildContext() (mesmo asset_id +
     * nova Corretiva criada dentro de RETRABALHO_JANELA_DIAS dias da
     * conclusao), invertido.
     *
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     * @return array{percentual: ?float, variacao_pp: ?float, serie_mensal: array{labels: array<int,string>, valores: array<int,float>}}
     */
    public function efetividadeManutencao(array $filtros): array
    {
        $percentual = $this->calcularEfetividade($filtros);

        $variacaoPp = null;
        $anterior = $this->periodoAnterior($filtros);
        if ($anterior && $percentual !== null) {
            $percentualAnterior = $this->calcularEfetividade(array_merge($filtros, $anterior));
            if ($percentualAnterior !== null) {
                $variacaoPp = round($percentual - $percentualAnterior, 1);
            }
        }

        $labels = [];
        $valores = [];
        foreach ($this->mesesDoPeriodo($filtros) as $mes) {
            $filtrosDoMes = array_merge($filtros, [
                'from' => $mes->toDateString(),
                'until' => $mes->copy()->endOfMonth()->toDateString(),
            ]);
            $labels[] = ucfirst($mes->translatedFormat('M/y'));
            $valores[] = $this->calcularEfetividade($filtrosDoMes) ?? 0.0;
        }

        return [
            'percentual' => $percentual,
            'variacao_pp' => $variacaoPp,
            'serie_mensal' => ['labels' => $labels, 'valores' => $valores],
        ];
    }

    /**
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     */
    private function calcularEfetividade(array $filtros): ?float
    {
        $concluidas = (clone $this->baseQuery($filtros))
            ->where('maintenance_type', MaintenanceOrder::TYPE_CORRECTIVE)
            ->whereIn('status', self::STATUS_CONCLUIDA)
            ->whereNotNull('finished_at')
            ->whereNotNull('asset_id')
            ->get(['id', 'asset_id', 'finished_at']);

        if ($concluidas->isEmpty()) {
            return null;
        }

        $assetIds = $concluidas->pluck('asset_id')->unique();

        $todasCorretivasPorAsset = MaintenanceOrder::query()
            ->where('tenant_id', $this->tenantId)
            ->whereIn('asset_id', $assetIds)
            ->where('maintenance_type', MaintenanceOrder::TYPE_CORRECTIVE)
            ->orderBy('created_at')
            ->get(['id', 'asset_id', 'created_at'])
            ->groupBy('asset_id');

        $comRetrabalho = 0;

        foreach ($concluidas as $osAntiga) {
            $todasDoAtivo = $todasCorretivasPorAsset->get($osAntiga->asset_id, collect());
            $limite = $osAntiga->finished_at->copy()->addDays(self::RETRABALHO_JANELA_DIAS);

            $teveRetrabalho = $todasDoAtivo->contains(fn (MaintenanceOrder $candidata) => $candidata->id !== $osAntiga->id
                && $candidata->created_at
                && $candidata->created_at->greaterThan($osAntiga->finished_at)
                && $candidata->created_at->lessThanOrEqualTo($limite)
            );

            if ($teveRetrabalho) {
                $comRetrabalho++;
            }
        }

        $semRetrabalho = $concluidas->count() - $comRetrabalho;

        return round(($semRetrabalho / $concluidas->count()) * 100, 1);
    }

    /**
     * MTBF (horas) agregado pros ativos relevantes ao filtro, usando
     * Asset::getMtbfHours() como fonte oficial (ja existe, ja trata null
     * quando falta dado). Ressalva: getMtbfHours() calcula sobre o
     * HISTORICO COMPLETO do ativo (delta total de horimetro / falhas
     * fechadas), sem recorte por from/until -- nao ha, hoje, como isolar
     * "MTBF so' deste periodo" com precisao sem reescrever o metodo do
     * Model. Media simples entre os ativos que tem MTBF calculavel
     * (ignora os que retornam null por falta de dado).
     *
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     * @return array{valor_horas: ?float, variacao_percentual: ?float}
     */
    public function mtbf(array $filtros): array
    {
        $valor = $this->calcularMtbfMedio($filtros);

        $variacao = null;
        $anterior = $this->periodoAnterior($filtros);
        if ($anterior && $valor !== null) {
            // MTBF e' historico-total por ativo (nao recortavel por periodo, ver
            // docblock acima) -- a "comparacao com periodo anterior" aqui so'
            // faz sentido se o CONJUNTO de ativos relevante mudar entre os dois
            // recortes (ex: filtro por asset_id/branch muda o universo). Sem
            // filtro de ativo/unidade, os dois periodos usam a mesma base de
            // ativos e o valor tende a ser identico -- comportamento esperado,
            // documentado aqui pra nao parecer bug.
            $valorAnterior = $this->calcularMtbfMedio(array_merge($filtros, $anterior));
            if ($valorAnterior !== null && $valorAnterior > 0) {
                $variacao = round((($valor - $valorAnterior) / $valorAnterior) * 100, 1);
            }
        }

        return [
            'valor_horas' => $valor,
            'variacao_percentual' => $variacao,
        ];
    }

    /**
     * @param  array{branchId: ?string, assetId: ?string}  $filtros
     */
    private function calcularMtbfMedio(array $filtros): ?float
    {
        $assetIds = $this->assetIdsRelevantes($filtros);
        if ($assetIds->isEmpty()) {
            return null;
        }

        $valores = Asset::query()
            ->whereIn('id', $assetIds)
            ->get()
            ->map(fn (Asset $asset) => $asset->getMtbfHours())
            ->filter(fn (?float $v) => $v !== null);

        if ($valores->isEmpty()) {
            return null;
        }

        return round($valores->avg(), 1);
    }

    /**
     * MTTR (horas): media de started_at ate finished_at, so' OS Corretiva
     * concluida no periodo. Retorna null (nao 0/erro) quando nao ha
     * corretiva concluida com as duas datas preenchidas no periodo --
     * equivalente ao NULLIF pedido no requisito tecnico, so' que resolvido
     * em PHP em vez de SQL puro.
     *
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     * @return array{valor_horas: ?float, variacao_percentual: ?float}
     */
    public function mttr(array $filtros): array
    {
        $valor = $this->calcularMttr($filtros);

        $variacao = null;
        $anterior = $this->periodoAnterior($filtros);
        if ($anterior && $valor !== null) {
            $valorAnterior = $this->calcularMttr(array_merge($filtros, $anterior));
            if ($valorAnterior !== null && $valorAnterior > 0) {
                $variacao = round((($valor - $valorAnterior) / $valorAnterior) * 100, 1);
            }
        }

        return [
            'valor_horas' => $valor,
            'variacao_percentual' => $variacao,
        ];
    }

    /**
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     */
    private function calcularMttr(array $filtros): ?float
    {
        $corretivas = (clone $this->baseQuery($filtros))
            ->where('maintenance_type', MaintenanceOrder::TYPE_CORRECTIVE)
            ->whereIn('status', self::STATUS_CONCLUIDA)
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->get(['started_at', 'finished_at']);

        if ($corretivas->isEmpty()) {
            return null;
        }

        $horasMedia = $corretivas->avg(
            fn (MaintenanceOrder $os) => $os->started_at->diffInMinutes($os->finished_at, true) / 60
        );

        return round($horasMedia, 2);
    }

    /**
     * Tempo total de parada nao planejada (horas) no periodo -- soma da
     * duracao de AssetDowntimeEvent com reason quebra/manutencao_corretiva
     * que se sobrepoe ao periodo (mesma logica de sobreposicao de
     * calcularDisponibilidade), mais serie mensal pro grafico de area do
     * rodape.
     *
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     * @return array{valor_horas: float, variacao_percentual: ?float, serie_mensal: array{labels: array<int,string>, valores: array<int,float>}}
     */
    public function tempoParadaNaoPlanejada(array $filtros): array
    {
        $valor = $this->calcularHorasParada($filtros);

        $variacao = null;
        $anterior = $this->periodoAnterior($filtros);
        if ($anterior) {
            $valorAnterior = $this->calcularHorasParada(array_merge($filtros, $anterior));
            if ($valorAnterior > 0) {
                $variacao = round((($valor - $valorAnterior) / $valorAnterior) * 100, 1);
            }
        }

        $labels = [];
        $valores = [];
        foreach ($this->mesesDoPeriodo($filtros) as $mes) {
            $filtrosDoMes = array_merge($filtros, [
                'from' => $mes->toDateString(),
                'until' => $mes->copy()->endOfMonth()->toDateString(),
            ]);
            $labels[] = ucfirst($mes->translatedFormat('M/y'));
            $valores[] = $this->calcularHorasParada($filtrosDoMes);
        }

        return [
            'valor_horas' => $valor,
            'variacao_percentual' => $variacao,
            'serie_mensal' => ['labels' => $labels, 'valores' => $valores],
        ];
    }

    /**
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     */
    private function calcularHorasParada(array $filtros): float
    {
        $assetIds = $this->assetIdsRelevantes($filtros);
        if ($assetIds->isEmpty()) {
            return 0.0;
        }

        $from = ! empty($filtros['from']) ? Carbon::parse($filtros['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $until = ! empty($filtros['until']) ? Carbon::parse($filtros['until'])->endOfDay() : now()->endOfDay();

        $minutos = AssetDowntimeEvent::query()
            ->where('tenant_id', $this->tenantId)
            ->whereIn('asset_id', $assetIds)
            ->whereIn('reason', [AssetDowntimeEvent::REASON_QUEBRA, AssetDowntimeEvent::REASON_MANUTENCAO_CORRETIVA])
            ->where('started_at', '<=', $until)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $from))
            ->get()
            ->sum(function (AssetDowntimeEvent $evento) use ($from, $until) {
                $inicio = $evento->started_at->max($from);
                // Evento ainda aberto (ended_at null) conta como parado ate agora, nunca
                // alem disso -- sem o min(now()) aqui, um evento aberto com $until no
                // futuro (mes corrente, por exemplo) contava parada "no futuro" que ainda
                // nem aconteceu, inflando o total de forma irreal.
                $fim = ($evento->ended_at ?? $until->copy()->min(now()))->min($until);

                return max(0, $inicio->diffInMinutes($fim, true));
            });

        return round($minutos / 60, 1);
    }

    /**
     * % de cada categoria de falha (failure_category) entre as OS
     * Corretivas concluidas no periodo, ordenado do maior pro menor.
     * failure_category so' comeca a ser preenchido a partir desta feature
     * (ver migration add_failure_category_to_maintenance_orders_table) --
     * se nenhuma OS do periodo tiver a categoria preenchida, devolve array
     * vazio (nao usa EquipmentDamage como fallback, pra nao misturar duas
     * fontes parciais e distorcer o percentual).
     *
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     * @return array<int, array{categoria: string, label: string, quantidade: int, percentual: float}>
     */
    public function principaisCausasFalha(array $filtros): array
    {
        $porCategoria = (clone $this->baseQuery($filtros))
            ->where('maintenance_type', MaintenanceOrder::TYPE_CORRECTIVE)
            ->whereIn('status', self::STATUS_CONCLUIDA)
            ->whereNotNull('failure_category')
            ->selectRaw('failure_category, count(*) as total')
            ->groupBy('failure_category')
            ->orderByDesc('total')
            ->pluck('total', 'failure_category');

        $totalGeral = $porCategoria->sum();

        if ($totalGeral === 0) {
            return [];
        }

        $labels = MaintenanceOrder::failureCategoryLabels();

        return $porCategoria->map(fn ($total, $categoria) => [
            'categoria' => $categoria,
            'label' => $labels[$categoria] ?? $categoria,
            'quantidade' => $total,
            'percentual' => round(($total / $totalGeral) * 100, 1),
        ])->values()->all();
    }

    /**
     * Bullets dinamicas de conclusao, comparando os KPIs ja calculados com
     * a meta do tenant (Tenant::getTarget()) e com a tendencia vs. periodo
     * anterior. Recebe os filtros (nao os resultados prontos) pra poder
     * calcular tudo internamente sem exigir do widget que ele already
     * tenha rodado os outros metodos antes -- widget de conclusao fica
     * simples (so' chama este metodo).
     *
     * @param  array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}  $filtros
     * @return array<int, string>
     */
    public function conclusoes(array $filtros): array
    {
        $tenant = Tenant::find($this->tenantId);
        $bullets = [];

        $disponibilidade = $this->disponibilidadeEquipamentos($filtros);
        if ($disponibilidade['percentual'] !== null) {
            $meta = $tenant?->getTarget('disponibilidade') ?? 90.0;
            $status = $disponibilidade['percentual'] >= $meta ? 'acima' : 'abaixo';
            $bullets[] = sprintf(
                'A disponibilidade da frota atingiu %s%%, %s da meta de %s%%.',
                number_format($disponibilidade['percentual'], 1, ',', '.'),
                $status,
                number_format($meta, 0, ',', '.')
            );
        }

        $realizada = $this->percentualManutencaoRealizada($filtros);
        if ($realizada['percentual'] !== null) {
            $meta = $tenant?->getTarget('manutencao_realizada') ?? 90.0;
            $status = $realizada['percentual'] >= $meta ? 'acima' : 'abaixo';
            $bullets[] = sprintf(
                'A manutenção realizada ficou em %s%% das OS planejadas, %s da meta de %s%%.',
                number_format($realizada['percentual'], 1, ',', '.'),
                $status,
                number_format($meta, 0, ',', '.')
            );
        }

        $efetividade = $this->efetividadeManutencao($filtros);
        if ($efetividade['percentual'] !== null) {
            $meta = $tenant?->getTarget('efetividade') ?? 85.0;
            $status = $efetividade['percentual'] >= $meta ? 'acima' : 'abaixo';
            $tendencia = $efetividade['variacao_pp'] !== null
                ? ($efetividade['variacao_pp'] >= 0 ? 'em alta' : 'em queda')
                : null;
            $bullets[] = sprintf(
                'Efetividade da manutenção em %s%%, %s da meta de %s%%%s.',
                number_format($efetividade['percentual'], 1, ',', '.'),
                $status,
                number_format($meta, 0, ',', '.'),
                $tendencia ? " ({$tendencia} vs. período anterior)" : ''
            );
        }

        $mtbf = $this->mtbf($filtros);
        $mttr = $this->mttr($filtros);
        if ($mtbf['valor_horas'] !== null || $mttr['valor_horas'] !== null) {
            $partes = [];
            if ($mtbf['valor_horas'] !== null) {
                $partes[] = sprintf('MTBF de %sh', number_format($mtbf['valor_horas'], 1, ',', '.'));
            }
            if ($mttr['valor_horas'] !== null) {
                $partes[] = sprintf('MTTR de %sh', number_format($mttr['valor_horas'], 2, ',', '.'));
            }
            $bullets[] = 'Confiabilidade da frota: '.implode(', ', $partes).'.';
        }

        return $bullets;
    }
}

<?php

namespace App\Services\Checklists;

use App\Models\Asset;
use App\Models\CkAnswer;
use App\Models\CkExecution;
use App\Models\MaintenanceOrder;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Visao geral dos checklists, em tres perguntas:
 *  1. COBERTURA: quais ativos tem checklist aplicavel e quais nao tem; dos que tem, quais nunca foram feitos ou estao atrasados.
 *  2. SERVICOS: quais OS foram feitas e se o checklist foi preenchido nelas (feito NESTA OS).
 *  3. CORRETIVAS x CHECKLIST: para cada corretiva, o que o checklist dizia ANTES dela (ultima OS e ultimo checklist), se algum item tem relacao com
 *     o problema e como ele estava (conforme, nao conforme, nao se aplica). Serve para achar o motivo da corretiva mesmo que ele ja tivesse sido visto.
 *
 * A relacao item x problema e uma HEURISTICA de palavras em comum (com radicais e poucos sinonimos): sempre mostra quais palavras casaram para a
 * pessoa julgar. Tudo e leitura, no escopo do tenant do usuario logado.
 *
 * @phpstan-type Filtros array{de?: ?string, ate?: ?string, grupo?: ?string, status?: ?string, dias?: int|string|null, checklist?: ?string, tipo?: ?string}
 */
class ChecklistVisaoGeral
{
    public const DIAS_PADRAO = 30;

    public const STATUS_COBERTURA = [
        'sem_checklist' => 'Sem checklist aplicável', 'nunca' => 'Com checklist, nunca feito', 'atrasado' => 'Atrasado', 'em_dia' => 'Em dia',
    ];

    public const TIPOS_DE_SERVICO = [MaintenanceOrder::TYPE_PREVENTIVE, MaintenanceOrder::TYPE_CORRECTIVE, MaintenanceOrder::TYPE_EMERGENCIA, MaintenanceOrder::TYPE_TROCA, MaintenanceOrder::TYPE_AVARIA];

    private const PALAVRAS_GENERICAS = [
        'com', 'sem', 'para', 'por', 'uma', 'uns', 'umas', 'dos', 'das', 'nos', 'nas', 'que', 'foi', 'esta', 'estava', 'esta', 'esse', 'essa', 'este', 'isso', 'ainda', 'muito', 'mais', 'menos',
        'equipamento', 'ativo', 'maquina', 'manutencao', 'corretiva', 'preventiva', 'geral', 'problema', 'verificar', 'conferir', 'estado', 'nivel', 'funcionando', 'funciona', 'defeito', 'falha',
        'quebrado', 'trocar', 'troca', 'trocado', 'realizar', 'executar', 'servico', 'cliente', 'necessario', 'apresentando', 'apresenta', 'precisa', 'fazer', 'feito', 'obra', 'campo', 'condicao',
        'dentro', 'fora', 'marca', 'item', 'itens', 'sistema', 'parte', 'peca', 'pecas', 'normal', 'anormal', 'visual', 'tecnico', 'tecnica', 'ordem', 'servicos', 'observacao', 'checklist',
    ];

    /** Sinonimos de campo: palavra escrita pelo mecanico => radical do item do checklist. Pouco e de proposito: a heuristica mostra o que casou. */
    private const SINONIMOS = [
        'radiador' => 'arrefe', 'refrigeracao' => 'arrefe', 'superaquecimento' => 'arrefe', 'aquecendo' => 'arrefe', 'aquecimento' => 'arrefe', 'esquentando' => 'arrefe',
        'diesel' => 'combus', 'gasolina' => 'combus', 'tanque' => 'combus', 'partida' => 'bateri', 'arranque' => 'bateri', 'vazando' => 'vazame', 'vazou' => 'vazame', 'vazar' => 'vazame',
        'disjuntor' => 'painel', 'quadro' => 'painel', 'pifou' => 'painel', 'correia' => 'correi', 'rolamento' => 'rolame', 'hidraulica' => 'hidrau', 'hidraulico' => 'hidrau', 'oleo' => 'oleo',
    ];

    public function __construct(private ChecklistAplicavel $aplicavel, private ChecklistExecucaoService $execucao) {}

    // ------------------------------------------------------------------ filtros

    /** @return array{de: ?string, ate: ?string, grupo: ?string, status: ?string, dias: int, checklist: ?string, tipo: ?string} */
    public function normalizar(array $f): array
    {
        $dias = (int) ($f['dias'] ?? self::DIAS_PADRAO);

        return [
            'de' => filled($f['de'] ?? null) ? (string) $f['de'] : null, 'ate' => filled($f['ate'] ?? null) ? (string) $f['ate'] : null,
            'grupo' => filled($f['grupo'] ?? null) ? (string) $f['grupo'] : null, 'status' => filled($f['status'] ?? null) ? (string) $f['status'] : null,
            'dias' => $dias > 0 ? $dias : self::DIAS_PADRAO, 'checklist' => filled($f['checklist'] ?? null) ? (string) $f['checklist'] : null, 'tipo' => filled($f['tipo'] ?? null) ? (string) $f['tipo'] : null,
        ];
    }

    private function limiteAtraso(array $f): CarbonInterface
    {
        return now()->subDays($f['dias'])->startOfDay();
    }

    // ------------------------------------------------------------------ 1. cobertura

    /** @return Builder<Asset> */
    public function queryAtivos(array $f): Builder
    {
        $f = $this->normalizar($f);
        $limite = $this->limiteAtraso($f);
        $q = Asset::query()->when($f['grupo'], fn (Builder $a, $g) => $a->where('asset_category_id', $g));

        return match ($f['status']) {
            'sem_checklist' => $this->aplicavel->filtrarAtivosComChecklist($q, false),
            'nunca' => $this->aplicavel->filtrarAtivosComChecklist($q)->whereDoesntHave('ckExecucoes', fn ($e) => $e->whereNotNull('finalizado_em')),
            'atrasado' => $this->aplicavel->filtrarAtivosComChecklist($q)->whereHas('ckExecucoes', fn ($e) => $e->whereNotNull('finalizado_em'))
                ->whereDoesntHave('ckExecucoes', fn ($e) => $e->where('finalizado_em', '>=', $limite)),
            'em_dia' => $this->aplicavel->filtrarAtivosComChecklist($q)->whereHas('ckExecucoes', fn ($e) => $e->where('finalizado_em', '>=', $limite)),
            default => $q,
        };
    }

    /** Totais da cobertura (ignora o filtro de status: sao os numeros que o filtro de status escolhe). @return array<string, int> */
    public function resumoCobertura(array $f): array
    {
        $f = $this->normalizar($f);
        $contar = fn (string $status) => $this->queryAtivos(['status' => $status] + $f)->count();

        return ['total' => $this->queryAtivos(['status' => null] + $f)->count()] + collect(array_keys(self::STATUS_COBERTURA))->mapWithKeys(fn ($s) => [$s => $contar($s)])->all();
    }

    /** @return array<string, mixed> uma linha da tabela de cobertura */
    public function linhaDoAtivo(Asset $a, array $f): array
    {
        $f = $this->normalizar($f);
        $modelos = $this->aplicavel->paraAtivo($a);
        $ultimo = CkExecution::query()->where('asset_id', $a->id)->whereNotNull('finalizado_em')->orderByDesc('finalizado_em')->first();
        $limite = $this->limiteAtraso($f);
        $status = $modelos->isEmpty() ? 'sem_checklist' : (! $ultimo ? 'nunca' : ($ultimo->finalizado_em >= $limite ? 'em_dia' : 'atrasado'));
        $ordens = MaintenanceOrder::query()->where('asset_id', $a->id)->whereIn('maintenance_type', self::TIPOS_DE_SERVICO)
            ->when($f['de'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))->when($f['ate'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
        $ids = (clone $ordens)->pluck('id');

        return [
            'ativo' => $a, 'grupo' => $a->category?->name, 'modelos' => $modelos->pluck('nome')->all(), 'status' => $status, 'ultimo' => $ultimo,
            'dias_desde' => $ultimo?->finalizado_em ? (int) $ultimo->finalizado_em->startOfDay()->diffInDays(now()->startOfDay()) : null,
            'os_no_periodo' => $ids->count(), 'os_com_checklist' => $ids->isEmpty() ? 0 : CkExecution::query()->whereIn('os_id', $ids)->whereNotNull('finalizado_em')->distinct()->count('os_id'),
        ];
    }

    public function ativos(array $f, int $porPagina = 25, string $pagina = 'pCob'): LengthAwarePaginator
    {
        return $this->queryAtivos($f)->with('category')->orderBy('name')->paginate($porPagina, pageName: $pagina)->through(fn (Asset $a) => $this->linhaDoAtivo($a, $f));
    }

    // ------------------------------------------------------------------ 2. servicos (OS x checklist)

    /** @return Builder<MaintenanceOrder> */
    public function queryServicos(array $f): Builder
    {
        $f = $this->normalizar($f);
        $q = MaintenanceOrder::query()->whereIn('maintenance_type', self::TIPOS_DE_SERVICO)->whereNotNull('asset_id')
            ->when($f['de'], fn ($o, $d) => $o->whereDate('created_at', '>=', $d))->when($f['ate'], fn ($o, $d) => $o->whereDate('created_at', '<=', $d))
            ->when($f['tipo'], fn ($o, $t) => $o->where('maintenance_type', $t))
            ->when($f['grupo'], fn ($o, $g) => $o->whereHas('asset', fn ($a) => $a->where('asset_category_id', $g)));

        $feito = fn ($e) => $e->selectRaw('1')->from('ck_executions')->whereColumn('ck_executions.os_id', 'maintenance_orders.id')->whereNotNull('ck_executions.finalizado_em');
        $comModelo = fn ($o) => $o->whereHas('asset', fn ($a) => $this->aplicavel->filtrarAtivosComChecklist($a));

        return match ($f['checklist']) {
            'feito' => $q->whereExists($feito),
            'pendente' => $q->tap($comModelo)->whereNotExists($feito),
            'sem_modelo' => $q->whereHas('asset', fn ($a) => $this->aplicavel->filtrarAtivosComChecklist($a, false)),
            default => $q,
        };
    }

    /** @return array<string, int> */
    public function resumoServicos(array $f): array
    {
        $f = $this->normalizar($f);

        return ['total' => $this->queryServicos(['checklist' => null] + $f)->count()] + collect(['feito', 'pendente', 'sem_modelo'])->mapWithKeys(fn ($c) => [$c => $this->queryServicos(['checklist' => $c] + $f)->count()])->all();
    }

    /** @return array<string, mixed> */
    public function linhaDaOs(MaintenanceOrder $os): array
    {
        $modelos = $os->asset ? $this->aplicavel->paraOrdemServico($os) : collect();
        $feitos = CkExecution::query()->where('os_id', $os->id)->whereNotNull('finalizado_em')->orderByDesc('finalizado_em')->get();
        $situacao = $feitos->isNotEmpty() ? 'feito' : ($modelos->isEmpty() ? 'sem_modelo' : 'pendente');

        return ['os' => $os, 'ativo' => $os->asset, 'modelos' => $modelos->pluck('nome')->all(), 'feitos' => $feitos, 'situacao' => $situacao];
    }

    public function servicos(array $f, int $porPagina = 25, string $pagina = 'pServ'): LengthAwarePaginator
    {
        return $this->queryServicos($f)->with('asset')->orderByDesc('created_at')->paginate($porPagina, pageName: $pagina)->through(fn (MaintenanceOrder $o) => $this->linhaDaOs($o));
    }

    // ------------------------------------------------------------------ 3. corretivas x checklist

    /** @return Builder<MaintenanceOrder> */
    public function queryCorretivas(array $f): Builder
    {
        $f = $this->normalizar($f);

        return MaintenanceOrder::query()->whereIn('maintenance_type', [MaintenanceOrder::TYPE_CORRECTIVE, MaintenanceOrder::TYPE_EMERGENCIA])->whereNotNull('asset_id')
            ->when($f['de'], fn ($o, $d) => $o->whereDate('created_at', '>=', $d))->when($f['ate'], fn ($o, $d) => $o->whereDate('created_at', '<=', $d))
            ->when($f['grupo'], fn ($o, $g) => $o->whereHas('asset', fn ($a) => $a->where('asset_category_id', $g)));
    }

    /**
     * Corretivas analisadas. Com `$diagnostico`, so as desse diagnostico (a analise e em PHP, entao filtra dentro das 500 mais recentes do periodo).
     */
    public function corretivas(array $f, ?string $diagnostico = null, int $porPagina = 15, string $pagina = 'pCor'): LengthAwarePaginator
    {
        if ($diagnostico === null || $diagnostico === '') {
            return $this->queryCorretivas($f)->with(['asset', 'reportedProblem'])->orderByDesc('created_at')->paginate($porPagina, pageName: $pagina)
                ->through(fn (MaintenanceOrder $o) => $this->analisarCorretiva($o));
        }

        $todas = $this->todasCorretivas($f, 500)->where('diagnostico', $diagnostico)->values();
        $atual = Paginator::resolveCurrentPage($pagina);

        return new \Illuminate\Pagination\LengthAwarePaginator($todas->forPage($atual, $porPagina)->values(), $todas->count(), $porPagina, $atual, ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pagina]);
    }

    /** Texto do problema: o que foi relatado, a descricao, a categoria da falha e a solucao (o que foi consertado diz muito sobre a causa). */
    public function textoDoProblema(MaintenanceOrder $os): string
    {
        return collect([$os->reportedProblem?->description, $os->description, $os->failure_category, $os->solution, $os->technical_notes])->filter()->implode(' ');
    }

    /**
     * O que o checklist dizia antes desta corretiva.
     *
     * @return array{os: MaintenanceOrder, ativo: ?Asset, problema: string, ultima_os: ?MaintenanceOrder, checklist_na_ultima_os: ?CkExecution, ultimo_checklist: ?CkExecution, dias_antes: ?int, tinha_modelo: bool, relacionados: array<int, array<string, mixed>>, diagnostico: string, rotulo: string}
     */
    public function analisarCorretiva(MaintenanceOrder $os): array
    {
        $criada = $os->created_at ?? now();
        $ultimaOs = MaintenanceOrder::query()->where('asset_id', $os->asset_id)->where('id', '!=', $os->id)->whereIn('maintenance_type', self::TIPOS_DE_SERVICO)
            ->where('created_at', '<', $criada)->orderByDesc('created_at')->first();
        $checklistNaUltimaOs = $ultimaOs ? CkExecution::query()->where('os_id', $ultimaOs->id)->whereNotNull('finalizado_em')->orderByDesc('finalizado_em')->first() : null;
        $ultimo = CkExecution::query()->where('asset_id', $os->asset_id)->whereNotNull('finalizado_em')->where('finalizado_em', '<=', $criada)->orderByDesc('finalizado_em')->first();
        $tinhaModelo = $os->asset ? $this->aplicavel->paraAtivo($os->asset)->isNotEmpty() : false;
        $problema = $this->textoDoProblema($os);
        $relacionados = $ultimo ? $this->relacionar($problema, $ultimo) : [];
        $dias = $ultimo?->finalizado_em ? (int) $ultimo->finalizado_em->startOfDay()->diffInDays($criada->copy()->startOfDay()) : null;

        [$diagnostico, $rotulo] = match (true) {
            ! $ultimo && ! $tinhaModelo => ['sem_checklist', 'Equipamento sem checklist: não há o que comparar'],
            ! $ultimo => ['nunca_conferido', 'Há checklist para este equipamento, mas nunca foi feito antes desta corretiva'],
            collect($relacionados)->contains(fn ($r) => $r['resposta'] === 'nao_conforme') => ['ja_registrado', 'O problema já estava registrado como NÃO CONFORME no último checklist'],
            $relacionados !== [] => ['conferido', 'Item relacionado foi conferido sem problema no último checklist: falha súbita ou conferência superficial'],
            default => ['sem_item', 'Nenhum item do último checklist cobre este problema: considere incluir um item'],
        };

        return [
            'os' => $os, 'ativo' => $os->asset, 'problema' => Str::limit($problema, 220), 'ultima_os' => $ultimaOs, 'checklist_na_ultima_os' => $checklistNaUltimaOs, 'ultimo_checklist' => $ultimo,
            'dias_antes' => $dias, 'tinha_modelo' => $tinhaModelo, 'relacionados' => $relacionados, 'diagnostico' => $diagnostico, 'rotulo' => $rotulo,
        ];
    }

    /**
     * Itens do checklist com palavras em comum com o problema, do mais grave (nao conforme) ao menos.
     *
     * @return array<int, array{item: string, secao: ?string, resposta: ?string, critico: bool, observacao: ?string, palavras: array<int, string>}>
     */
    public function relacionar(string $problema, CkExecution $execucao): array
    {
        $termos = $this->radicais($problema);

        if ($termos === []) {
            return [];
        }

        $ordem = ['nao_conforme' => 0, 'conforme' => 1, 'na' => 2];

        return CkAnswer::query()->where('execution_id', $execucao->id)->get()->map(function (CkAnswer $r) use ($termos) {
            $itemRad = $this->radicais($r->item_descricao.' '.$r->secao_titulo.' '.$r->observacao);
            $comuns = array_values(array_intersect_key($termos, $itemRad));

            return $comuns === [] ? null : [
                'item' => $r->item_descricao, 'secao' => $r->secao_titulo, 'resposta' => $r->resposta, 'critico' => (bool) $r->critico, 'observacao' => $r->observacao, 'palavras' => $comuns,
            ];
        })->filter()->sortBy(fn ($r) => [$ordem[$r['resposta']] ?? 3, -count($r['palavras'])])->values()->all();
    }

    /**
     * Palavras que importam do texto: sem acento, minusculas, sem as genericas, com sinonimos e reduzidas a um radical de 6 letras (vazamento e vazamentos casam).
     *
     * @return array<string, string> radical => palavra original (como apareceu no texto)
     */
    public function radicais(string $texto): array
    {
        $out = [];

        foreach (preg_split('/[^a-z0-9]+/', Str::ascii(Str::lower($texto))) ?: [] as $palavra) {
            if (strlen($palavra) < 4 || in_array($palavra, self::PALAVRAS_GENERICAS, true) || is_numeric($palavra)) {
                continue;
            }

            $radical = self::SINONIMOS[$palavra] ?? (strlen($palavra) > 6 ? substr($palavra, 0, 6) : $palavra);
            $out[$radical] ??= $palavra;
        }

        return $out;
    }

    /** Resumo para os cartoes: quantas corretivas caem em cada diagnostico no filtro. @return array<string, int> */
    public function resumoCorretivas(array $f): array
    {
        $r = ['total' => 0, 'sem_checklist' => 0, 'nunca_conferido' => 0, 'ja_registrado' => 0, 'conferido' => 0, 'sem_item' => 0];

        $this->queryCorretivas($f)->with(['asset', 'reportedProblem'])->orderByDesc('created_at')->limit(500)->get()->each(function (MaintenanceOrder $o) use (&$r) {
            $r['total']++;
            $r[$this->analisarCorretiva($o)['diagnostico']]++;
        });

        return $r;
    }

    /** @return Collection<int, array<string, mixed>> todas as corretivas analisadas (para exportar; limite de seguranca) */
    public function todasCorretivas(array $f, int $limite = 2000): Collection
    {
        return $this->queryCorretivas($f)->with(['asset', 'reportedProblem'])->orderByDesc('created_at')->limit($limite)->get()->map(fn (MaintenanceOrder $o) => $this->analisarCorretiva($o));
    }
}

<?php

namespace App\Services\Checklists;

use App\Models\Asset;
use App\Models\ChecklistGroup;
use App\Models\CkExecution;
use App\Models\CkItem;
use App\Models\CkSection;
use App\Models\CkTemplate;
use App\Models\MaintenanceOrder;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * O que a tela da OS mostra sobre os checklists por grupo: os modelos que valem para o ativo (pela regra do grupo de checklist, ver
 * ChecklistAplicavel), de onde vem cada vinculo, e os ultimos checklists feitos nesse equipamento. Devolve nulo quando a pessoa nao
 * pode ver nem executar (a secao some, sem aviso). So le.
 */
class ChecklistsDaOs
{
    public function __construct(private ChecklistAplicavel $aplicavel, private ChecklistExecucaoService $execucao) {}

    /**
     * Com a OS, cada modelo traz `feito` (a execucao feita NESTA OS, a mais recente) ou nulo se esta pendente, e `pendentes` conta os que faltam.
     *
     * @return array{ativo: Asset, modelos: array<int, array{template: CkTemplate, itens: int, criticos: int, motivo: string, feito: ?CkExecution}>, ultimas: Collection, pode_executar: bool, pendentes: ?int}|null
     */
    public function paraOrdemServico(MaintenanceOrder $os): ?array
    {
        return $this->paraAtivo($os->asset_id, $os);
    }

    /**
     * Quantos checklists que valem para o equipamento da OS ainda nao foram feitos NESTA OS. Nao depende de permissao: e a regra que
     * segura a conclusao da OS no Modo Campo, e so conta se o contrato tem as chaves dos checklists.
     */
    public function pendentesDaOs(MaintenanceOrder $os): int
    {
        $tenant = Tenant::withoutGlobalScopes()->with(['plan' => fn ($q) => $q->withoutGlobalScopes()])->find($os->tenant_id);

        if (! $tenant?->plan?->hasFeature(CkTemplate::FEATURE_KEY) || ! $tenant->plan->hasFeature('tabela_ck_execucoes')) {
            return 0;
        }

        return $this->aplicavel->paraOrdemServico($os)->filter(fn (CkTemplate $t) => $this->feitoNaOs($os, $t) === null)->count();
    }

    private function feitoNaOs(MaintenanceOrder $os, CkTemplate $t): ?CkExecution
    {
        return CkExecution::withoutGlobalScopes()->where('tenant_id', $os->tenant_id)->where('os_id', $os->id)->where('template_id', $t->id)
            ->whereNotNull('finalizado_em')->orderByDesc('finalizado_em')->first();
    }

    /** @return array{ativo: Asset, modelos: array<int, array{template: CkTemplate, itens: int, criticos: int, motivo: string, feito: ?CkExecution}>, ultimas: Collection, pode_executar: bool, pendentes: ?int}|null */
    public function paraAtivo(?string $assetId, ?MaintenanceOrder $os = null): ?array
    {
        $podeExecutar = Gate::allows('create', CkExecution::class);

        if (! $assetId || ! (Gate::allows('viewAny', CkExecution::class) || $podeExecutar)) {
            return null;
        }

        $ativo = Asset::find($assetId);

        if (! $ativo) {
            return null;
        }

        $modelos = $this->aplicavel->paraAtivo($ativo)->map(function (CkTemplate $t) use ($ativo, $os) {
            $secoes = CkSection::withoutGlobalScopes()->where('template_id', $t->id)->whereNull('deleted_at')->pluck('id');
            $itens = CkItem::withoutGlobalScopes()->whereIn('section_id', $secoes)->where('ativo', true)->whereNull('deleted_at');

            return [
                'template' => $t, 'itens' => (clone $itens)->count(), 'criticos' => (clone $itens)->where('critico', true)->count(),
                'motivo' => $this->motivo($t, $ativo), 'feito' => $os ? $this->feitoNaOs($os, $t) : null,
                'secoes' => $this->secoesComItens($t),
            ];
        })->all();

        $ultimas = CkExecution::query()->where('asset_id', $ativo->id)->orderByDesc('finalizado_em')->limit(5)->get();

        return [
            'ativo' => $ativo, 'modelos' => $modelos, 'ultimas' => $ultimas, 'pode_executar' => $podeExecutar,
            'pendentes' => $os ? count(array_filter($modelos, fn ($m) => $m['feito'] === null)) : null,
        ];
    }

    /**
     * Itens do modelo, por secao, para mostrar dentro da propria OS (o tecnico ve o que vai conferir sem abrir a execucao).
     *
     * @return array<int, array{titulo: string, itens: array<int, array{descricao: string, critico: bool}>}>
     */
    private function secoesComItens(CkTemplate $t): array
    {
        $secoes = CkSection::withoutGlobalScopes()->where('template_id', $t->id)->whereNull('deleted_at')->orderBy('ordem')->get();
        $itens = CkItem::withoutGlobalScopes()->whereIn('section_id', $secoes->pluck('id'))->where('ativo', true)->whereNull('deleted_at')->orderBy('ordem')->get()->groupBy('section_id');

        return $secoes->map(fn (CkSection $s) => [
            'titulo' => $s->titulo,
            'itens' => ($itens[$s->id] ?? collect())->map(fn (CkItem $i) => ['descricao' => $i->descricao, 'critico' => (bool) $i->critico])->all(),
        ])->filter(fn (array $s) => $s['itens'] !== [])->values()->all();
    }

    /** Texto do campo "Grupo do Ativo" na OS: o grupo de checklist; sem ele, o grupo de equipamento que esta valendo para os checklists. */
    public function textoGrupoDoAtivo(?Asset $ativo): string
    {
        if (! $ativo) {
            return 'Sem grupo definido';
        }

        if ($ativo->checklistGroup?->name) {
            return $ativo->checklistGroup->name;
        }

        $equipamento = $ativo->asset_category_id ? $ativo->category?->name : null;

        return $equipamento && $this->aplicavel->paraAtivo($ativo)->isNotEmpty()
            ? $equipamento.' (grupo de equipamento — os checklists vêm dele)'
            : 'Sem grupo definido';
    }

    private function motivo(CkTemplate $t, Asset $ativo): string
    {
        if ($t->checklist_group_id && $t->checklist_group_id === $ativo->checklist_group_id) {
            $nome = ChecklistGroup::withoutGlobalScopes()->where('tenant_id', $ativo->tenant_id)->whereKey($t->checklist_group_id)->value('name');

            return 'grupo de checklist: '.($nome ?? '—');
        }

        return 'grupo de equipamento: '.($t->grupo?->name ?? '—');
    }

    /**
     * Checklists para IMPRIMIR junto com a OS (o mecanico que trabalha no papel): cada modelo que vale para o ativo, ja em paginas de
     * `$linhasPorPagina` linhas (cabecalho de secao + itens) para caber numa folha A4. Quem imprime a OS ja ve o conteudo dela, entao aqui
     * so vale a chave do contrato (nao a permissao individual de checklist). Sem a chave ou sem modelo aplicavel, devolve lista vazia.
     *
     * @return array<int, array{template: CkTemplate, itens: int, criticos: int, paginas: array<int, array<int, array<string, mixed>>>}>
     */
    public function paraImpressao(MaintenanceOrder $os, int $linhasPorPagina = 20): array
    {
        $tenant = Tenant::withoutGlobalScopes()->with(['plan' => fn ($q) => $q->withoutGlobalScopes()])->find($os->tenant_id);

        if (! $tenant?->plan?->hasFeature(CkTemplate::FEATURE_KEY)) {
            return [];
        }

        $saida = [];

        foreach ($this->aplicavel->paraOrdemServico($os) as $template) {
            $itens = $this->execucao->itensAtivos($template);

            if ($itens->isEmpty()) {
                continue;
            }

            $paginas = [[]];
            $tituloSecao = null;
            $secaoAtual = null;
            $n = 0;

            foreach ($itens as $item) {
                $novaSecao = $secaoAtual !== $item->section_id;
                $secaoAtual = $item->section_id;
                $tituloSecao = $item->secao?->titulo;
                $linhasNecessarias = $novaSecao ? 2 : 1; // titulo da secao + o item (o titulo nunca fica sozinho no fim da pagina)

                if (count(end($paginas)) + $linhasNecessarias > $linhasPorPagina) {
                    $paginas[] = [];
                    $novaSecao = true; // pagina nova repete o titulo da secao (continuacao)
                }

                if ($novaSecao) {
                    $paginas[count($paginas) - 1][] = ['tipo' => 'secao', 'titulo' => (string) $tituloSecao];
                }

                $paginas[count($paginas) - 1][] = ['tipo' => 'item', 'n' => ++$n, 'item' => $item];
            }

            $saida[] = ['template' => $template, 'itens' => $itens->count(), 'criticos' => $itens->where('critico', true)->count(), 'paginas' => $paginas];
        }

        return $saida;
    }
}

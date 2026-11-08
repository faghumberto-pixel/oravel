<?php

namespace App\Support;

use App\Models\CkAnswer;
use App\Services\Checklists\ChecklistVisaoGeral;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Excel (OpenSpout) da visao geral de checklists: uma aba por pergunta (cobertura, servicos, corretivas x checklist), sempre com os filtros da tela. */
class CkVisaoGeralExport
{
    private const LIMITE = 5000;

    /** @param array<string, mixed> $filtros */
    public static function xlsx(string $aba, array $filtros, ChecklistVisaoGeral $svc): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'ckvg').'.xlsx';
        $w = new Writer;
        $w->openToFile($caminho);

        match ($aba) {
            'servicos' => self::servicos($w, $filtros, $svc),
            'corretivas' => self::corretivas($w, $filtros, $svc),
            default => self::cobertura($w, $filtros, $svc),
        };

        $w->close();

        return $caminho;
    }

    private static function cobertura(Writer $w, array $f, ChecklistVisaoGeral $svc): void
    {
        $w->getCurrentSheet()->setName('Cobertura');
        $w->addRow(Row::fromValues(['Ativo', 'Patrimônio/Tag', 'Grupo', 'Checklists aplicáveis', 'Situação', 'Último checklist', 'Dias desde o último', 'Resultado do último', 'OS no período', 'OS com checklist feito']));

        $svc->queryAtivos($f)->with('category')->orderBy('name')->limit(self::LIMITE)->get()->each(function ($a) use ($w, $f, $svc) {
            $l = $svc->linhaDoAtivo($a, $f);
            $w->addRow(Row::fromValues([
                $a->name, $a->patrimonio ?: $a->tag, $l['grupo'], implode(' | ', $l['modelos']), ChecklistVisaoGeral::STATUS_COBERTURA[$l['status']], $l['ultimo']?->finalizado_em?->format('d/m/Y H:i'),
                $l['dias_desde'], $l['ultimo'] ? (CkExecucaoExport::STATUS[$l['ultimo']->status] ?? $l['ultimo']->status) : null, $l['os_no_periodo'], $l['os_com_checklist'],
            ]));
        });
    }

    private static function servicos(Writer $w, array $f, ChecklistVisaoGeral $svc): void
    {
        $w->getCurrentSheet()->setName('Serviços');
        $w->addRow(Row::fromValues(['OS', 'Data', 'Tipo', 'Status', 'Ativo', 'Checklists aplicáveis', 'Checklist nesta OS', 'Feito em', 'Executor', 'Resultado']));
        $rotulo = ['feito' => 'Feito', 'pendente' => 'Pendente', 'sem_modelo' => 'Sem checklist aplicável'];

        $svc->queryServicos($f)->with('asset')->orderByDesc('created_at')->limit(self::LIMITE)->get()->each(function ($os) use ($w, $svc, $rotulo) {
            $l = $svc->linhaDaOs($os);
            $e = $l['feitos']->first();
            $w->addRow(Row::fromValues([
                $os->os_number, $os->created_at?->format('d/m/Y'), $os->maintenance_type, $os->status, $os->asset?->name, implode(' | ', $l['modelos']), $rotulo[$l['situacao']],
                $e?->finalizado_em?->format('d/m/Y H:i'), $e?->executor?->name, $e ? (CkExecucaoExport::STATUS[$e->status] ?? $e->status) : null,
            ]));
        });
    }

    private static function corretivas(Writer $w, array $f, ChecklistVisaoGeral $svc): void
    {
        $w->getCurrentSheet()->setName('Corretivas x checklist');
        $w->addRow(Row::fromValues(['OS corretiva', 'Data', 'Ativo', 'Problema', 'Última OS antes', 'Checklist na última OS', 'Último checklist antes', 'Dias antes', 'Resultado do último', 'Diagnóstico', 'Itens relacionados', 'Pendências do último checklist']));
        $resposta = ['conforme' => 'conforme', 'nao_conforme' => 'NÃO CONFORME', 'na' => 'não se aplica'];

        foreach ($svc->todasCorretivas($f, self::LIMITE) as $c) {
            $ultimo = $c['ultimo_checklist'];
            $pendencias = $ultimo ? CkAnswer::query()->where('execution_id', $ultimo->id)->where('resposta', 'nao_conforme')->pluck('item_descricao')->implode(' | ') : null;
            $w->addRow(Row::fromValues([
                $c['os']->os_number, $c['os']->created_at?->format('d/m/Y'), $c['ativo']?->name, $c['problema'],
                $c['ultima_os'] ? $c['ultima_os']->os_number.' ('.$c['ultima_os']->maintenance_type.', '.$c['ultima_os']->created_at?->format('d/m/Y').')' : null,
                $c['ultima_os'] ? ($c['checklist_na_ultima_os'] ? 'Feito' : ($c['tinha_modelo'] ? 'Não feito' : 'Sem checklist aplicável')) : null,
                $ultimo?->finalizado_em?->format('d/m/Y H:i'), $c['dias_antes'], $ultimo ? (CkExecucaoExport::STATUS[$ultimo->status] ?? $ultimo->status) : null, $c['rotulo'],
                collect($c['relacionados'])->map(fn ($r) => $r['item'].' ['.($resposta[$r['resposta']] ?? '—').'; palavras: '.implode(', ', $r['palavras']).']')->implode(' | '), $pendencias,
            ]));
        }
    }
}

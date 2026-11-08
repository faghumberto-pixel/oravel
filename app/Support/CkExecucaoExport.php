<?php

namespace App\Support;

use App\Models\CkExecution;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Exportacao Excel (OpenSpout, o mesmo do restante do projeto) das execucoes de checklist: uma aba de execucoes e uma de respostas. */
class CkExecucaoExport
{
    public const STATUS = [
        CkExecution::STATUS_EM_ANDAMENTO => 'Em andamento', CkExecution::STATUS_APROVADO => 'Aprovado',
        CkExecution::STATUS_APROVADO_COM_RESSALVA => 'Aprovado com ressalva', CkExecution::STATUS_REPROVADO => 'Reprovado',
    ];

    public static function cabecalhoExecucoes(): array
    {
        return ['Data', 'Ativo', 'Patrimônio/Tag', 'Grupo', 'Checklist', 'Versão', 'Executor', 'Resultado', 'Leitura (h/km)', 'Itens críticos não conformes', 'Pendências', 'OS do atendimento', 'OS corretiva aberta', 'Origem', 'Observações'];
    }

    public static function cabecalhoRespostas(): array
    {
        return ['Data', 'Ativo', 'Checklist', 'Seção', 'Item', 'Crítico', 'Resposta', 'Valor', 'Observação', 'Referência'];
    }

    public static function xlsx(Builder $query): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'ckexec').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($caminho);
        $writer->getCurrentSheet()->setName('Execuções');
        $writer->addRow(Row::fromValues(self::cabecalhoExecucoes()));

        $ids = [];

        (clone $query)->with(['asset.category', 'executor', 'ordemServico', 'os'])->withCount([
            'respostas as criticos_nc_count' => fn ($q) => $q->where('critico', true)->where('resposta', 'nao_conforme'),
            'respostas as pendencias_count' => fn ($q) => $q->where('critico', false)->where('resposta', 'nao_conforme'),
        ])->chunk(500, function ($execucoes) use ($writer, &$ids) {
            foreach ($execucoes as $e) {
                $ids[] = $e->id;
                $writer->addRow(Row::fromValues([
                    $e->finalizado_em?->format('d/m/Y H:i'), $e->asset?->name, $e->asset?->patrimonio ?: $e->asset?->tag, $e->asset?->category?->name,
                    $e->template_nome, $e->versao_template, $e->executor?->name, self::STATUS[$e->status] ?? $e->status,
                    $e->leitura_medidor !== null ? (float) $e->leitura_medidor : null, $e->criticos_nc_count, $e->pendencias_count,
                    $e->os?->os_number, $e->ordemServico?->os_number, $e->origemRotulo(), $e->observacoes,
                ]));
            }
        });

        $writer->addNewSheetAndMakeItCurrent()->setName('Respostas');
        $writer->addRow(Row::fromValues(self::cabecalhoRespostas()));

        foreach (array_chunk($ids, 500) as $lote) {
            CkExecution::withoutGlobalScopes()->whereIn('id', $lote)->with(['asset', 'respostas'])->orderBy('finalizado_em')->get()->each(function (CkExecution $e) use ($writer) {
                foreach ($e->respostas as $r) {
                    $writer->addRow(Row::fromValues([
                        $e->finalizado_em?->format('d/m/Y H:i'), $e->asset?->name, $e->template_nome, $r->secao_titulo, $r->item_descricao, $r->critico ? 'Sim' : 'Não',
                        match ($r->resposta) {
                            'conforme' => 'Conforme', 'nao_conforme' => 'Não conforme', 'na' => 'Não se aplica', default => ''
                        },
                        $r->valor_numerico !== null ? (float) $r->valor_numerico : $r->valor_texto, $r->observacao, $r->referencia_manual,
                    ]));
                }
            });
        }

        $writer->close();

        return $caminho;
    }
}

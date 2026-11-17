<?php

namespace App\Services;

use App\Models\AIAnalysis;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Carbon;

/**
 * Analise da O.S. como um todo via Claude: prazo e SLA, custo comparado ao historico, pecas e materiais, checklist,
 * pendencias, retrabalho e qualidade do registro. Diferente do diagnostico de avaria (EquipmentDamageDiagnosisService),
 * que olha so' o defeito. So consulta: nunca altera a O.S.
 */
class MaintenanceOrderAnalysisService
{
    public function __construct(private AnthropicApiClient $client) {}

    public function analyze(MaintenanceOrder $os, string $userId): AIAnalysis
    {
        $context = $this->buildContext($os);

        $analysis = AIAnalysis::create([
            'tenant_id' => $os->tenant_id,
            'user_id' => $userId,
            'type' => AIAnalysis::TYPE_ORDEM_SERVICO,
            'maintenance_order_id' => $os->id,
            'context' => $context,
            'status' => AIAnalysis::STATUS_PENDENTE,
        ]);

        $result = $this->client->sendJson(
            $this->systemPrompt(),
            "Dados da O.S.:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            8000,
            ['area' => 'manutencao', 'etapa' => 'analise_os', 'tenant_id' => $os->tenant_id],
        );

        if (! $result['ok']) {
            $analysis->update([
                'status' => AIAnalysis::STATUS_FALHOU,
                'error' => $result['error'],
                'response' => $result['text'] !== null ? ['raw' => $result['text']] : null,
            ]);

            return $analysis;
        }

        $parsed = $result['data'];

        $analysis->update(['status' => AIAnalysis::STATUS_CONCLUIDA, 'response' => $parsed]);

        return $analysis;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            Você é um gerente de manutenção com 20 anos de experiência em locadoras de máquinas pesadas
            (geradores, plataformas elevatórias, empilhadeiras, compressores, retroescavadeiras). Receberá
            os dados de UMA ordem de serviço (O.S.) inteira: prazo e SLA, custos, peças e materiais,
            checklist, pendências, histórico de status e o histórico do equipamento. Avalie a O.S. como um
            todo, como quem revisa o trabalho antes de liberar o equipamento ou fechar a conta.

            Regras: use somente os dados recebidos; nunca invente números, peças ou fatos. Se um dado não
            existir, diga que não há informação. Compare custo e tempo com o histórico quando houver.
            Em "vinculos_de_retrabalho", "retorno_de" é a O.S. original da qual esta é retorno e "retornos_posteriores"
            são as O.S. que voltaram a este serviço. Se o vínculo já existe nos dados, NÃO peça para vincular de novo e NÃO sugira marcar a
            O.S. original como retrabalho: analise a recorrência (intervalo, mesmo componente, causa raiz tratada ou não). Só peça vínculo
            quando houver indício de retorno SEM o vínculo registrado.
            Seja objetivo: cada texto com no máximo 3 frases. Responda APENAS com um objeto JSON válido, sem markdown, sem crases, sem texto antes ou depois,
            exatamente neste formato (todas as chaves sempre presentes):

            {
              "resumo": "string (3 a 5 frases: o que é a O.S., em que ponto está, o que mais importa)",
              "avaliacao_geral": "boa | atencao | critica",
              "prazo_e_sla": "string",
              "custo": "string (custo da O.S. e comparação com o histórico do equipamento e do tipo de serviço)",
              "pecas_e_materiais": "string",
              "checklist_e_registro": ["string"],
              "riscos": ["string"],
              "pontos_de_atencao": ["string"],
              "risco_retrabalho": "baixo | medio | alto",
              "motivo_risco_retrabalho": "string",
              "recomendacoes": ["string"],
              "proximos_passos": [{"acao": "string", "responsavel_sugerido": "string", "prazo": "string"}]
            }
            PROMPT;
    }

    /**
     * Vinculo de retrabalho nos dois sentidos: de qual O.S. esta e' retorno e quais O.S. posteriores sao retorno dela. Sem isso a IA
     * so' via o indicador "retrabalho" e pedia pra vincular O.S. que ja' estavam vinculadas (ou marcar a original como retrabalho).
     *
     * @return array{retorno_de: ?array<string, mixed>, retornos_posteriores: array<int, array<string, mixed>>}
     */
    public function vinculosDeRetorno(MaintenanceOrder $os): array
    {
        $fmt = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : null;
        $base = fn () => MaintenanceOrder::withoutGlobalScopes()->where('tenant_id', $os->tenant_id);

        $pai = $os->parent_os_id ? $base()->find($os->parent_os_id, ['os_number', 'description', 'created_at', 'finished_at']) : null;
        $filhos = $base()->where('parent_os_id', $os->id)->orderBy('created_at')->get(['os_number', 'description', 'created_at', 'finished_at']);

        return [
            'retorno_de' => $pai ? ['os' => $pai->os_number, 'descricao' => $pai->description, 'concluida_em' => $fmt($pai->finished_at ?? $pai->created_at)] : null,
            'retornos_posteriores' => $filhos->map(fn ($f) => ['os' => $f->os_number, 'descricao' => $f->description, 'aberta_em' => $fmt($f->created_at)])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function buildContext(MaintenanceOrder $os): array
    {
        $os->loadMissing(['asset', 'client', 'reportedProblem', 'technician']);
        $asset = $os->asset;
        $fmt = fn ($d) => $d?->format('d/m/Y H:i');

        $checklist = $os->checklists()->where('is_template', false)->get(['category', 'item_name', 'status', 'is_completed']);
        $materiais = $os->materials()->get(['name', 'quantity', 'unit_price']);
        $pendencias = $os->pendencias()->get(['description', 'status', 'resolved_at', 'resolution_notes']);
        $historicoStatus = $os->statusHistories()->orderBy('created_at')->limit(30)->get(['old_status', 'new_status', 'observation', 'segment_seconds', 'created_at']);
        $timers = $os->slaTimers()->get();

        $outras = $asset
            ? $asset->maintenanceOrders()->where('id', '!=', $os->id)->orderByDesc('created_at')->limit(10)
                ->get(['os_number', 'maintenance_type', 'description', 'status', 'is_rework', 'total_order_cost', 'hours_spent', 'created_at', 'finished_at'])
            : collect();

        $mesmoTipo = $outras->where('maintenance_type', $os->maintenance_type)->where('total_order_cost', '>', 0);

        return [
            'os' => [
                'numero' => $os->os_number,
                'tipo' => $os->maintenance_type,
                'status' => $os->status,
                'status_interno' => $os->internal_status,
                'prioridade' => $os->priority,
                'descricao' => $os->description,
                'problema_reportado' => $os->reportedProblem?->description,
                'categoria_falha' => $os->failure_category,
                'solucao' => $os->solution,
                'notas_tecnicas' => $os->technical_notes,
                'cliente' => $os->client?->name,
                'tecnico' => $os->technician?->name,
                'aberta_em' => $fmt($os->created_at),
                'agendada_para' => $fmt($os->scheduled_at),
                'iniciada_em' => $fmt($os->started_at),
                'concluida_em' => $fmt($os->finished_at),
                'horas_gastas' => (float) $os->hours_spent,
                'retrabalho' => (bool) $os->is_rework,
                'horimetro_entrada' => $os->horimetro_entry,
                'custo' => [
                    'mao_de_obra' => (float) $os->labor_cost,
                    'material' => (float) $os->material_cost,
                    'logistica' => (float) $os->logistics_cost,
                    'total' => (float) $os->total_order_cost,
                ],
                'fotos_evidencia' => $os->evidences()->count(),
            ],
            'ativo' => $asset ? [
                'patrimonio' => $asset->patrimonio,
                'nome' => $asset->name,
                'categoria' => $asset->category?->name ?? $asset->asset_category,
                'horimetro_atual' => $asset->horimetro_atual,
                'valor_aquisicao' => $asset->acquisition_value,
            ] : null,
            'sla' => $timers->map(fn ($t) => [
                'relogio' => $t->clock,
                'meta_minutos' => $t->target_minutes,
                'gasto_minutos' => $t->spent_minutes,
                'resultado' => $t->result,
                'vence_em' => $fmt($t->due_at),
                'pausado' => $t->paused_at !== null,
            ])->all(),
            'checklist' => [
                'total' => $checklist->count(),
                'conformes' => $checklist->where('is_completed', true)->count(),
                'nao_conformes' => $checklist->where('status', 'nao_conforme')->pluck('item_name')->all(),
                'pendentes' => $checklist->where('is_completed', false)->where('status', '!=', 'nao_conforme')->count(),
            ],
            'materiais' => $materiais->map(fn ($m) => ['nome' => $m->name, 'quantidade' => (float) $m->quantity, 'preco_unitario' => (float) $m->unit_price])->all(),
            'pendencias' => $pendencias->map(fn ($p) => ['descricao' => $p->description, 'status' => $p->status, 'resolvida' => $p->resolved_at !== null, 'resolucao' => $p->resolution_notes])->all(),
            'historico_status' => $historicoStatus->map(fn ($h) => ['de' => $h->old_status, 'para' => $h->new_status, 'observacao' => $h->observation, 'segundos_no_status' => $h->segment_seconds, 'quando' => $fmt($h->created_at)])->all(),
            'historico_do_ativo' => $outras->map(fn ($o) => [
                'os' => $o->os_number, 'tipo' => $o->maintenance_type, 'descricao' => $o->description, 'status' => $o->status,
                'retrabalho' => (bool) $o->is_rework, 'custo_total' => (float) $o->total_order_cost, 'horas' => (float) $o->hours_spent,
                'data' => $fmt($o->finished_at ?? $o->created_at),
            ])->all(),
            'vinculos_de_retrabalho' => $this->vinculosDeRetorno($os),
            'comparativo_mesmo_tipo' => [
                'qtd_os_anteriores' => $mesmoTipo->count(),
                'custo_medio' => $mesmoTipo->isNotEmpty() ? round((float) $mesmoTipo->avg('total_order_cost'), 2) : null,
            ],
        ];
    }
}

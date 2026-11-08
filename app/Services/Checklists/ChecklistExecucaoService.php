<?php

namespace App\Services\Checklists;

use App\Models\Asset;
use App\Models\CatalogoCumprimento;
use App\Models\CatalogoRequisito;
use App\Models\CkAnswer;
use App\Models\CkExecution;
use App\Models\CkItem;
use App\Models\CkSection;
use App\Models\CkTemplate;
use App\Models\MaintenanceOrder;
use App\Models\User;
use App\Services\Catalogo\Aptidao\AptidaoGate;
use App\Services\Catalogo\Aptidao\ChecklistAptidaoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Execucao de um checklist em um ativo (C3).
 *
 * Resultado: algum item CRITICO nao conforme = reprovado; so itens nao criticos nao conformes = aprovado com ressalva (as respostas nao
 * conformes sao as pendencias); senao aprovado. Texto, tipo e criticidade de cada item vao para a resposta (snapshot).
 *
 * Aptidao (G1): com a chave do catalogo ligada, cada item ligado a um requisito do catalogo grava um cumprimento (conforme = OK; critico
 * nao conforme = reprovado, o que deixa o ativo Inapto e abre a OS corretiva pelo gatilho que ja existe, com downtime pela regra da OS).
 * Item critico sem requisito equivalente ganha um requisito do tenant (RequisitoDoItem). Falha nessa integracao nunca derruba o checklist.
 */
class ChecklistExecucaoService
{
    public const RESPOSTA_CONFORME = 'conforme';

    public const RESPOSTA_NAO_CONFORME = 'nao_conforme';

    public const RESPOSTA_NA = 'na';

    public const COLECAO_EVIDENCIAS = 'checklist_evidencias';

    public function __construct(
        private ChecklistAplicavel $aplicavel,
        private RequisitoDoItem $requisitos,
        private ChecklistAptidaoService $aptidao,
    ) {}

    /**
     * @param  array{respostas?: array<string, array{resposta?: ?string, valor_numerico?: mixed, valor_texto?: ?string, foto?: ?UploadedFile, observacao?: ?string}>, leitura_medidor?: mixed, medidor?: ?string, assinatura?: ?string, observacoes?: ?string, iniciado_em?: mixed}  $dados
     *
     * @throws ValidationException
     */
    public function registrar(Asset $ativo, CkTemplate $template, array $dados, ?User $usuario = null): CkExecution
    {
        $usuario ??= auth()->user();

        if (! $this->aplicavel->paraAtivo($ativo)->contains('id', $template->id)) {
            throw ValidationException::withMessages(['template' => 'Este checklist não se aplica a este ativo.']);
        }

        $itens = $this->itensAtivos($template);
        $respostas = (array) ($dados['respostas'] ?? []);
        $papel = ($dados['origem'] ?? null) === CkExecution::ORIGEM_PAPEL_IA;
        $osId = $this->osDoAtendimento($ativo, $dados['os_id'] ?? null);
        $this->validar($itens, $respostas, $dados, $papel);

        $execucao = DB::transaction(function () use ($ativo, $template, $itens, $respostas, $dados, $usuario, $papel, $osId) {
            $execucao = CkExecution::withoutGlobalScopes()->create([
                'tenant_id' => $ativo->tenant_id, 'asset_id' => $ativo->id, 'template_id' => $template->id, 'template_nome' => $template->nome,
                'versao_template' => $template->versao, 'executor_id' => $usuario?->id, 'medidor' => $dados['medidor'] ?? null,
                'leitura_medidor' => filled($dados['leitura_medidor'] ?? null) ? (float) str_replace(',', '.', (string) $dados['leitura_medidor']) : null,
                'status' => CkExecution::STATUS_EM_ANDAMENTO, 'iniciado_em' => $dados['iniciado_em'] ?? now(), 'assinatura' => $dados['assinatura'] ?? null,
                'observacoes' => filled($dados['observacoes'] ?? null) ? trim($dados['observacoes']) : null,
                'os_id' => $osId, 'registro_origem' => $papel ? CkExecution::ORIGEM_PAPEL_IA : null, 'folhas' => $papel ? array_values((array) ($dados['folhas'] ?? [])) : null,
            ]);

            $temCritico = false;
            $temNaoConforme = false;

            foreach ($itens as $item) {
                $r = $respostas[$item->id] ?? null;

                if (! $this->respondido($r)) {
                    continue;
                }

                $naoConforme = ($r['resposta'] ?? null) === self::RESPOSTA_NAO_CONFORME;
                $temNaoConforme = $temNaoConforme || $naoConforme;
                $temCritico = $temCritico || ($naoConforme && $item->critico);

                $foto = $r['foto'] ?? null;
                $caminho = $foto instanceof UploadedFile ? $foto->store('ck-execucoes/'.$ativo->tenant_id.'/'.$execucao->id, 'local') : null;
                // Papel: a evidencia do item nao conforme e a propria foto da folha (a IA leu dela e a pessoa conferiu).
                $fotosDoItem = $caminho ? [$caminho] : ($papel && $naoConforme ? array_values((array) ($dados['folhas'] ?? [])) : null);

                CkAnswer::withoutGlobalScopes()->create([
                    'tenant_id' => $ativo->tenant_id, 'execution_id' => $execucao->id, 'item_id' => $item->id,
                    'secao_titulo' => $item->secao?->titulo, 'item_descricao' => $item->descricao, 'tipo_resposta' => $item->tipo_resposta,
                    'critico' => $item->critico, 'referencia_manual' => $item->referencia_manual,
                    'resposta' => $r['resposta'] ?? null,
                    'valor_numerico' => $item->tipo_resposta === 'numerico' ? (float) str_replace(',', '.', (string) $r['valor_numerico']) : null,
                    'valor_texto' => filled($r['valor_texto'] ?? null) ? trim((string) $r['valor_texto']) : null,
                    'fotos' => $fotosDoItem ?: null,
                    'observacao' => filled($r['observacao'] ?? null) ? trim((string) $r['observacao']) : null,
                ]);
            }

            $execucao->update([
                'status' => $temCritico ? CkExecution::STATUS_REPROVADO : ($temNaoConforme ? CkExecution::STATUS_APROVADO_COM_RESSALVA : CkExecution::STATUS_APROVADO),
                'finalizado_em' => now(),
            ]);

            return $execucao;
        });

        $this->integrarComAptidao($execucao, $ativo, $usuario);

        return $execucao->refresh();
    }

    /** A OS do atendimento so vale se e do mesmo tenant e do mesmo ativo (nunca amarra o checklist a uma OS de outro equipamento). */
    private function osDoAtendimento(Asset $ativo, mixed $osId): ?string
    {
        if (! is_string($osId) || $osId === '') {
            return null;
        }

        return MaintenanceOrder::withoutGlobalScopes()->where('tenant_id', $ativo->tenant_id)->where('asset_id', $ativo->id)->whereKey($osId)->value('id');
    }

    /** Respostas da execucao lidas sem o escopo do usuario logado: o servico tambem roda em fila/job, sem usuario do mesmo tenant. */
    private function respostasDe(CkExecution $execucao)
    {
        return CkAnswer::withoutGlobalScopes()->where('execution_id', $execucao->id);
    }

    /** Itens ativos do template, na ordem das secoes e dos itens (secoes e itens arquivados ficam de fora). */
    public function itensAtivos(CkTemplate $template)
    {
        $secoes = CkSection::withoutGlobalScopes()->where('template_id', $template->id)->whereNull('deleted_at')->orderBy('ordem')->get()->keyBy('id');

        return CkItem::withoutGlobalScopes()->whereIn('section_id', $secoes->keys())->where('ativo', true)->whereNull('deleted_at')
            ->get()->each(fn (CkItem $i) => $i->setRelation('secao', $secoes[$i->section_id]))
            ->sortBy(fn (CkItem $i) => [$secoes[$i->section_id]->ordem, $i->ordem])->values();
    }

    private function respondido(?array $r): bool
    {
        return $r !== null && (filled($r['resposta'] ?? null) || filled($r['valor_numerico'] ?? null) || filled($r['valor_texto'] ?? null) || ($r['foto'] ?? null) instanceof UploadedFile);
    }

    /** @throws ValidationException */
    private function validar($itens, array $respostas, array $dados, bool $papel = false): void
    {
        $erro = fn (string $msg) => throw ValidationException::withMessages(['itens' => $msg]);

        foreach ($itens as $item) {
            $r = $respostas[$item->id] ?? null;
            $descricao = $item->descricao;

            if (! $this->respondido($r)) {
                if ($item->obrigatorio) {
                    $erro("Responda o item: {$descricao}");
                }

                continue;
            }

            switch ($item->tipo_resposta) {
                case 'conforme_nao_conforme_na':
                    if (! in_array($r['resposta'] ?? null, [self::RESPOSTA_CONFORME, self::RESPOSTA_NAO_CONFORME, self::RESPOSTA_NA], true)) {
                        $erro("Responda o item: {$descricao}");
                    }

                    if (! $papel && ($r['resposta'] ?? null) === self::RESPOSTA_NAO_CONFORME && $item->exige_foto_se_nao_conforme && ! (($r['foto'] ?? null) instanceof UploadedFile)) {
                        $erro("Tire a foto do problema: {$descricao}");
                    }
                    break;
                case 'numerico':
                    if (! filled($r['valor_numerico'] ?? null) || ! is_numeric(str_replace(',', '.', (string) $r['valor_numerico']))) {
                        $erro("Informe o valor medido: {$descricao}");
                    }
                    break;
                case 'selecao':
                    if (! in_array($r['valor_texto'] ?? null, (array) $item->opcoes, true)) {
                        $erro("Escolha uma opção: {$descricao}");
                    }
                    break;
                case 'texto':
                    if (! filled($r['valor_texto'] ?? null)) {
                        $erro("Escreva a resposta: {$descricao}");
                    }
                    break;
                case 'foto':
                    if (! $papel && ! (($r['foto'] ?? null) instanceof UploadedFile)) {
                        $erro("Tire a foto: {$descricao}");
                    }
                    break;
            }
        }

        // No papel a assinatura esta na folha impressa (a foto dela fica como evidencia): so a tela exige assinar.
        if ($papel) {
            if (empty($dados['folhas'])) {
                throw ValidationException::withMessages(['folhas' => 'Anexe a foto da folha preenchida.']);
            }

            return;
        }

        if (! str_starts_with((string) ($dados['assinatura'] ?? ''), 'data:image')) {
            throw ValidationException::withMessages(['assinatura' => 'Assine na caixa de assinatura e confirme.']);
        }
    }

    /**
     * Grava os cumprimentos no G1 (aptidao + OS automatica) e liga a OS aberta a execucao. Nunca derruba o checklist.
     * Conforme em item ligado = OK; critico nao conforme = reprovado (impeditivo); nao critico nao conforme so reprova requisito de ALERTA.
     */
    private function integrarComAptidao(CkExecution $execucao, Asset $ativo, ?User $usuario): void
    {
        if (! AptidaoGate::ligada($ativo->tenant_id)) {
            return;
        }

        try {
            $porRequisito = [];

            foreach ($this->respostasDe($execucao)->get() as $resposta) {
                $item = $resposta->item_id ? CkItem::withoutGlobalScopes()->find($resposta->item_id) : null;

                if (! $item || ! in_array($resposta->resposta, [self::RESPOSTA_CONFORME, self::RESPOSTA_NAO_CONFORME], true)) {
                    continue;
                }

                $naoConforme = $resposta->resposta === self::RESPOSTA_NAO_CONFORME;
                $requisito = ($naoConforme && $item->critico)
                    ? $this->requisitos->garantir($item, $ativo)
                    : $this->requisitoDoGrupo($item, $ativo);

                if (! $requisito) {
                    continue;
                }

                $reprova = $naoConforme && ($item->critico || $requisito->efeito === 'ALR');
                $anterior = $porRequisito[$requisito->id]['resultado'] ?? null;

                if ($anterior === CatalogoCumprimento::REPROVADO) {
                    continue; // reprovado vence conforme quando dois itens alimentam o mesmo requisito
                }

                $porRequisito[$requisito->id] = [
                    'resultado' => $reprova ? CatalogoCumprimento::REPROVADO : CatalogoCumprimento::OK,
                    'observacao' => $reprova ? ($resposta->observacao ?: 'Checklist "'.$execucao->template_nome.'": '.$resposta->item_descricao) : null,
                ];
            }

            if ($porRequisito === []) {
                return;
            }

            $this->aptidao->registrar($ativo, $porRequisito, $usuario, 'checklist_equipamento');

            $os = CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $ativo->id)->whereIn('requisito_id', array_keys($porRequisito))
                ->where('resultado', CatalogoCumprimento::REPROVADO)->whereNotNull('maintenance_order_id')->where('realizado_em', '>=', $execucao->iniciado_em)
                ->orderByDesc('realizado_em')->value('maintenance_order_id');

            if ($os && $execucao->status === CkExecution::STATUS_REPROVADO) {
                $ordem = MaintenanceOrder::withoutGlobalScopes()->find($os);
                $execucao->update(['ordem_servico_id' => $os]);
                $this->anexarEvidencias($execucao, $ordem);
            }
        } catch (\Throwable $e) {
            Log::error('Checklist de equipamento: falha na integracao com a aptidao', ['execucao_id' => $execucao->id, 'erro' => $e->getMessage()]);
        }
    }

    /** Requisito ja ligado ao item e que pertence ao grupo de catalogo do ativo (sem criar nada). */
    private function requisitoDoGrupo(CkItem $item, Asset $ativo): ?CatalogoRequisito
    {
        if (! $item->catalogo_requisito_id || ! $ativo->asset_category_id) {
            return null;
        }

        return CatalogoRequisito::withoutGlobalScopes()->where('tenant_id', $ativo->tenant_id)->where('grupo_id', $ativo->asset_category_id)
            ->where('id', $item->catalogo_requisito_id)->where('ativo', true)->whereNull('deleted_at')->first();
    }

    /**
     * "Abrir OS corretiva agora": cria a OS ja com os itens criticos reprovados, as observacoes e as fotos. A OS corretiva abre o
     * downtime do ativo pela regra que ja existe na propria OS. Idempotente: se a execucao ja tem OS, devolve a mesma.
     */
    public function abrirOsCorretiva(CkExecution $execucao): MaintenanceOrder
    {
        if ($execucao->ordem_servico_id && ($existente = MaintenanceOrder::withoutGlobalScopes()->find($execucao->ordem_servico_id))) {
            return $existente;
        }

        $criticos = $this->respostasDe($execucao)->where('critico', true)->where('resposta', self::RESPOSTA_NAO_CONFORME)->get();

        if ($criticos->isEmpty()) {
            throw ValidationException::withMessages(['os' => 'Esta execução não tem item crítico reprovado.']);
        }

        $ativo = Asset::withoutGlobalScopes()->find($execucao->asset_id);
        $linhas = $criticos->map(fn (CkAnswer $a) => '- '.$a->item_descricao.($a->observacao ? ' — '.$a->observacao : ''))->implode("\n");

        $os = MaintenanceOrder::create([
            'tenant_id' => $execucao->tenant_id, 'asset_id' => $execucao->asset_id, 'client_id' => $ativo?->client_id,
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => 'Aberto', 'origin' => 'checklist_equipamento',
            'description' => "Checklist \"{$execucao->template_nome}\" (v{$execucao->versao_template}) reprovado. Itens críticos não conformes:\n{$linhas}",
        ]);

        $execucao->update(['ordem_servico_id' => $os->id]);
        $this->anexarEvidencias($execucao, $os);

        return $os;
    }

    /** Copia as fotos dos itens criticos reprovados para a OS (uma vez so por foto). */
    public function anexarEvidencias(CkExecution $execucao, ?MaintenanceOrder $os): void
    {
        if (! $os) {
            return;
        }

        $jaAnexadas = $os->media()->where('collection_name', self::COLECAO_EVIDENCIAS)->get()->map(fn ($m) => $m->getCustomProperty('origem'))->filter()->all();

        foreach ($this->respostasDe($execucao)->where('critico', true)->where('resposta', self::RESPOSTA_NAO_CONFORME)->get() as $a) {
            foreach ((array) $a->fotos as $caminho) {
                $marca = $execucao->id.':'.$caminho;

                if (in_array($marca, $jaAnexadas, true) || ! Storage::disk('local')->exists($caminho)) {
                    continue;
                }

                $os->addMedia(Storage::disk('local')->path($caminho))->preservingOriginal()->withCustomProperties(['origem' => $marca, 'item' => $a->item_descricao])
                    ->toMediaCollection(self::COLECAO_EVIDENCIAS);
            }
        }

        $os->unsetRelation('media'); // quem recebe a OS de volta le as fotos novas, nao a relacao em cache
    }
}

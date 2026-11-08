<?php

namespace App\Services\Checklists;

use App\Models\CkAnswer;
use App\Models\CkExecution;
use App\Models\CkItem;
use App\Models\CkSection;
use App\Models\CkTemplate;
use App\Models\FrotaChecklist;
use App\Models\FrotaItemModeloChecklist as ItemFrota;
use App\Models\FrotaModeloChecklist;
use App\Models\FrotaRespostaChecklist as RespostaFrota;
use Illuminate\Support\Facades\DB;

/**
 * Espelho do checklist de saida/retorno da FROTA no motor novo (C4). A Frota segue exatamente como esta (odometro, pareamento,
 * bloqueio, bateria, pneu): este servico so COPIA, para o historico e o relatorio de checklists ficarem unificados. Nada da Frota
 * e alterado ou apagado, e a copia e idempotente (legado_tipo/legado_id). A troca da Frota para ler os modelos novos fica para depois
 * de validada a copia.
 *
 * Modelos copiados entram INATIVOS e sem grupo: nao se aplicam a nenhum ativo, so existem para o dia da troca.
 * Fotos da Frota nao sao copiadas (ficam no checklist original).
 */
class FrotaParaChecklists
{
    public const LEGADO_CHECKLIST = 'frota_checklist';

    public const LEGADO_MODELO = 'frota_modelo';

    private const STATUS = [
        FrotaChecklist::OK => CkExecution::STATUS_APROVADO,
        FrotaChecklist::ATENCAO => CkExecution::STATUS_APROVADO_COM_RESSALVA,
        FrotaChecklist::BLOQUEADO => CkExecution::STATUS_REPROVADO,
        FrotaChecklist::LIBERADO => CkExecution::STATUS_APROVADO_COM_RESSALVA,
    ];

    public function espelharChecklist(FrotaChecklist $c): CkExecution
    {
        return DB::transaction(function () use ($c) {
            $modelo = $c->modelo_id ? FrotaModeloChecklist::withoutGlobalScopes()->find($c->modelo_id) : null;
            $tipo = $c->tipo === FrotaChecklist::TIPO_RETORNO ? 'Retorno' : 'Saída';
            $quando = $c->concluido_em ?? $c->created_at ?? now();
            $obs = trim((string) $c->observacoes);

            if ($c->situacao === FrotaChecklist::LIBERADO && filled($c->motivo_liberacao)) {
                $obs = trim($obs."\nLiberado após bloqueio: ".$c->motivo_liberacao);
            }

            $dados = [
                'asset_id' => $c->ativo_id, 'template_id' => null, 'template_nome' => 'Frota · '.($modelo?->nome ?? 'checklist').' ('.$tipo.')',
                'versao_template' => 1, 'executor_id' => $c->preenchido_por, 'medidor' => 'km', 'leitura_medidor' => $c->odometro,
                'status' => self::STATUS[$c->situacao] ?? CkExecution::STATUS_APROVADO, 'iniciado_em' => $quando, 'finalizado_em' => $quando,
                'assinatura' => $c->assinatura, 'observacoes' => $obs !== '' ? $obs : null, 'ordem_servico_id' => $c->ordem_servico_id,
            ];

            $exec = CkExecution::withoutGlobalScopes()->where('tenant_id', $c->tenant_id)->where('legado_tipo', self::LEGADO_CHECKLIST)->where('legado_id', $c->id)->first();

            if ($exec) {
                $exec->update($dados);
            } else {
                $exec = CkExecution::withoutGlobalScopes()->create($dados + ['tenant_id' => $c->tenant_id, 'legado_tipo' => self::LEGADO_CHECKLIST, 'legado_id' => $c->id]);
            }

            CkAnswer::withoutGlobalScopes()->where('execution_id', $exec->id)->delete();

            foreach (RespostaFrota::query()->where('checklist_id', $c->id)->get() as $r) {
                $numerico = $r->valor_numerico !== null || filled($r->unidade);

                CkAnswer::withoutGlobalScopes()->create([
                    'tenant_id' => $c->tenant_id, 'execution_id' => $exec->id, 'item_id' => null,
                    'secao_titulo' => ItemFrota::categoriaLabels()[$r->categoria_registrada] ?? ucfirst((string) $r->categoria_registrada),
                    'item_descricao' => $r->descricao_registrada, 'tipo_resposta' => $numerico ? 'numerico' : 'conforme_nao_conforme_na',
                    'critico' => $r->gravidade_registrada === ItemFrota::GRAVIDADE_CRITICA,
                    'resposta' => match ($r->resultado) {
                        RespostaFrota::OK => 'conforme', RespostaFrota::PROBLEMA => 'nao_conforme', default => 'na',
                    },
                    'valor_numerico' => $numerico ? $r->valor_numerico : null, 'observacao' => $r->observacao,
                ]);
            }

            return $exec;
        });
    }

    public function espelharModelo(FrotaModeloChecklist $m): CkTemplate
    {
        return DB::transaction(function () use ($m) {
            $existente = CkTemplate::withoutGlobalScopes()->withTrashed()->where('tenant_id', $m->tenant_id)->where('legado_tipo', self::LEGADO_MODELO)->where('legado_id', $m->id)->first();

            if ($existente) {
                return $existente; // a copia nao e atualizada depois: o que a empresa fizer nela e dela
            }

            $perfil = $m->tipo_veiculo ? (FrotaModeloChecklist::tipoVeiculoLabels()[$m->tipo_veiculo] ?? $m->tipo_veiculo) : 'todos os veículos';
            $template = CkTemplate::withoutGlobalScopes()->create([
                'tenant_id' => $m->tenant_id, 'grupo_id' => null, 'nome' => 'Frota · '.$m->nome, 'tipo' => 'saida', 'periodicidade' => 'por_uso', 'versao' => 1,
                'ativo' => false, 'origem' => CkTemplate::ORIGEM_CUSTOMIZADO, 'personalizado' => false,
                'observacoes' => 'Cópia do modelo de checklist da Frota ('.($m->tipo === FrotaModeloChecklist::TIPO_COMPLETO ? 'completo' : 'rápido').'; '.$perfil.'), feita para a migração. '
                    .'Inativa e sem grupo: não se aplica a nenhum ativo. A Frota segue usando o modelo original até a troca.',
                'legado_tipo' => self::LEGADO_MODELO, 'legado_id' => $m->id,
            ]);

            $porCategoria = ItemFrota::query()->where('modelo_id', $m->id)->orderBy('ordem')->get()->groupBy(fn (ItemFrota $i) => $i->categoria ?: 'outros');
            $ordemSecao = 0;

            foreach ($porCategoria as $categoria => $itens) {
                $secao = CkSection::withoutGlobalScopes()->create(['tenant_id' => $m->tenant_id, 'template_id' => $template->id, 'titulo' => ItemFrota::categoriaLabels()[$categoria] ?? ucfirst((string) $categoria), 'ordem' => $ordemSecao++]);

                foreach ($itens->values() as $i => $item) {
                    CkItem::withoutGlobalScopes()->create([
                        'tenant_id' => $m->tenant_id, 'section_id' => $secao->id, 'descricao' => $item->descricao,
                        'tipo_resposta' => match ($item->tipo_resposta) {
                            ItemFrota::RESPOSTA_NUMERO => 'numerico', ItemFrota::RESPOSTA_TEXTO => 'texto', default => 'conforme_nao_conforme_na',
                        },
                        'unidade' => $item->unidade, 'obrigatorio' => $item->gravidade !== ItemFrota::GRAVIDADE_INFORMATIVA,
                        'critico' => $item->gravidade === ItemFrota::GRAVIDADE_CRITICA, 'exige_foto_se_nao_conforme' => (bool) $item->exige_foto_se_problema,
                        'ordem' => $i, 'ativo' => true, 'personalizado' => false,
                    ]);
                }
            }

            return $template;
        });
    }

    /**
     * Copia tudo da Frota de um tenant. Com $simular, roda e desfaz (nada fica gravado).
     *
     * @return array{modelos: int, checklists: int, ja_copiados: int}
     */
    public function migrarTenant(string $tenantId, bool $simular = false): array
    {
        $r = ['modelos' => 0, 'checklists' => 0, 'ja_copiados' => 0];

        DB::beginTransaction();

        try {
            foreach (FrotaModeloChecklist::withoutGlobalScopes()->where('tenant_id', $tenantId)->get() as $modelo) {
                $existia = CkTemplate::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tenantId)->where('legado_tipo', self::LEGADO_MODELO)->where('legado_id', $modelo->id)->exists();
                $this->espelharModelo($modelo);
                $existia ? $r['ja_copiados']++ : $r['modelos']++;
            }

            foreach (FrotaChecklist::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('created_at')->get() as $checklist) {
                $existia = CkExecution::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('legado_tipo', self::LEGADO_CHECKLIST)->where('legado_id', $checklist->id)->exists();
                $this->espelharChecklist($checklist);
                $existia ? $r['ja_copiados']++ : $r['checklists']++;
            }

            $simular ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return $r;
    }
}

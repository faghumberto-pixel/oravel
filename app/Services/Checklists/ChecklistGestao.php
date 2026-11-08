<?php

namespace App\Services\Checklists;

use App\Models\CkItem;
use App\Models\CkSection;
use App\Models\CkTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gestao dos checklists pelo tenant (C2): duplicar, ativar/desativar, restaurar padrao e controle de versao. Tudo que muda
 * conteudo sobe a versao; execucoes antigas guardam snapshot e nunca mudam.
 */
class ChecklistGestao
{
    public function __construct(private ChecklistInstalador $instalador) {}

    /**
     * Copia independente (secoes e itens ativos). Nasce customizada, sem vinculo com o padrao, na versao 1. O vinculo de cada item
     * com o requisito do catalogo e mantido.
     */
    public function duplicar(CkTemplate $origem, string $nome): CkTemplate
    {
        $nome = trim($nome);

        if ($nome === '') {
            throw ValidationException::withMessages(['nome' => 'Informe o nome do novo checklist.']);
        }

        return DB::transaction(function () use ($origem, $nome) {
            $copia = CkTemplate::withoutGlobalScopes()->create([
                'tenant_id' => $origem->tenant_id, 'grupo_id' => $origem->grupo_id, 'checklist_group_id' => $origem->checklist_group_id, 'nome' => $nome, 'tipo' => $origem->tipo, 'periodicidade' => $origem->periodicidade,
                'intervalo_horimetro_km' => $origem->intervalo_horimetro_km, 'versao' => 1, 'ativo' => true, 'origem' => CkTemplate::ORIGEM_CUSTOMIZADO,
                'referencia_manual' => $origem->referencia_manual, 'observacoes' => $origem->observacoes, 'slug_template' => null, 'versao_template' => null, 'personalizado' => true,
            ]);

            $secoes = CkSection::withoutGlobalScopes()->where('template_id', $origem->id)->whereNull('deleted_at')->orderBy('ordem')->get();

            foreach ($secoes as $s) {
                $nova = CkSection::withoutGlobalScopes()->create(['tenant_id' => $origem->tenant_id, 'template_id' => $copia->id, 'titulo' => $s->titulo, 'ordem' => $s->ordem, 'slug_template' => null]);

                foreach (CkItem::withoutGlobalScopes()->where('section_id', $s->id)->whereNull('deleted_at')->orderBy('ordem')->get() as $i) {
                    $dados = $i->only(['descricao', 'tipo_resposta', 'unidade', 'opcoes', 'obrigatorio', 'critico', 'exige_foto_se_nao_conforme', 'referencia_manual', 'validar_norma', 'catalogo_requisito_id', 'ordem', 'ativo']);
                    CkItem::withoutGlobalScopes()->create($dados + ['tenant_id' => $origem->tenant_id, 'section_id' => $nova->id, 'slug_template' => null, 'personalizado' => true]);
                }
            }

            return $copia;
        });
    }

    public function alternarAtivo(CkTemplate $template): bool
    {
        $template->update(['ativo' => ! $template->ativo]);

        return (bool) $template->ativo;
    }

    public function podeRestaurar(CkTemplate $template): bool
    {
        return ! $template->trashed() && $this->instalador->temPadrao($template);
    }

    /**
     * Restaurar padrao: sobrescreve a personalizacao do tenant pelo YAML. Sempre com justificativa e registro (antes e depois).
     *
     * @throws ValidationException
     */
    public function restaurarPadrao(CkTemplate $template, string $justificativa, ?User $usuario = null): CkTemplate
    {
        $justificativa = trim($justificativa);

        if (mb_strlen($justificativa) < 5) {
            throw ValidationException::withMessages(['justificativa' => 'Informe a justificativa (mínimo 5 caracteres).']);
        }

        $antes = ['nome' => $template->nome, 'versao' => $template->versao, 'personalizado' => (bool) $template->personalizado, 'itens' => $template->itens()->count()];
        $restaurado = $this->instalador->restaurar($template);

        activity('checklist_equipamento')
            ->performedOn($restaurado)
            ->causedBy($usuario ?? auth()->user())
            ->withProperties(['antes' => $antes, 'depois' => ['nome' => $restaurado->nome, 'versao' => $restaurado->versao, 'itens' => $restaurado->itens()->count()], 'justificativa' => $justificativa])
            ->event('padrao_restaurado')
            ->log("Checklist '{$restaurado->nome}' restaurado ao padrão");

        return $restaurado;
    }

    /**
     * Chamado depois de salvar a tela de edicao: se o conteudo mudou em relacao a $hashAntes, sobe a versao e marca como
     * personalizado (o padrao nao sobrescreve mais). Sem mudanca real, nada acontece (salvar sem editar nao gasta versao).
     */
    public function registrarEdicao(CkTemplate $template, ?string $hashAntes): bool
    {
        if ($hashAntes === null || $template->hashConteudo() === $hashAntes) {
            return false;
        }

        $template->forceFill(['versao' => $template->versao + 1, 'personalizado' => true])->save();

        return true;
    }
}

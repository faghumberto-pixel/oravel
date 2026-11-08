<?php

namespace App\Services\Catalogo;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoItem;
use App\Models\CatalogoModel;
use App\Models\CatalogoPreventiva;
use App\Models\CatalogoRequisito;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * G1 fase 2 -- gestao do catalogo pelo tenant: clonar, arquivar/restaurar, excluir definitivo (so se nunca usado),
 * deteccao de edicao sensivel (exige justificativa) e trilha de auditoria (activity log: usuario, antes, depois, justificativa).
 */
class CatalogoGestao
{
    /** Efeitos "obrigatorios": remover/desativar/rebaixar um requisito desses e edicao sensivel. */
    private const EFEITOS_IMPEDITIVOS = ['IMP', 'DOC', 'INSP'];

    public const AVISO_RESPONSAVEL_TECNICO = 'Edicao sensivel: valide com o responsavel tecnico antes de aplicar.';

    // ------------------------------------------------------------------ consultas

    public function ativosNoGrupo(AssetCategory $grupo): int
    {
        return Asset::withoutGlobalScopes()->where('tenant_id', $grupo->tenant_id)
            ->where('asset_category_id', $grupo->id)->whereNull('deleted_at')->count();
    }

    /** Grupo ja foi usado por algum ativo (inclusive excluido)? Entao so pode ser arquivado. */
    public function grupoTemHistorico(AssetCategory $grupo): bool
    {
        return Asset::withoutGlobalScopes()->where('tenant_id', $grupo->tenant_id)->where('asset_category_id', $grupo->id)->exists();
    }

    public function itemTemHistorico(CatalogoItem $item): bool
    {
        return CatalogoRequisito::withoutGlobalScopes()->withTrashed()->where('item_id', $item->id)->exists();
    }

    // ------------------------------------------------------------------ clonar

    public function clonarGrupo(AssetCategory $origem, string $nome, ?User $usuario = null): AssetCategory
    {
        return DB::transaction(function () use ($origem, $nome, $usuario) {
            $slug = $this->slugLivre($origem->tenant_id, Str::slug($nome));

            $novo = AssetCategory::withoutGlobalScopes()->create([
                'tenant_id' => $origem->tenant_id, 'name' => $nome, 'slug' => $slug, 'description' => $origem->description,
                'is_active' => true, 'medidor' => $origem->medidor, 'criticidade_padrao' => $origem->criticidade_padrao,
                'exige_operador' => $origem->exige_operador, 'ordem' => (int) $origem->ordem + 1,
                'origem' => CatalogoModel::ORIGEM_TENANT, 'slug_template' => null, 'versao_template' => null, 'personalizado' => true,
            ]);

            foreach (['atributos', 'requisitos', 'preventivas', 'operadores', 'pecasCriticas', 'slaPoliticas', 'regras'] as $relacao) {
                foreach ($origem->{$relacao}()->get() as $filho) {
                    $copia = $filho->replicate();
                    $copia->grupo_id = $novo->id;
                    $copia->origem = CatalogoModel::ORIGEM_TENANT;
                    $copia->slug_template = null;
                    $copia->versao_template = null;
                    $copia->personalizado = true;
                    $copia->saveQuietly();
                }
            }

            $this->log($novo, 'Catalogo: grupo clonado', $usuario, ['clonado_de' => $origem->id, 'clonado_de_nome' => $origem->name]);

            return $novo;
        });
    }

    // ------------------------------------------------------------------ arquivar / restaurar / purgar

    /**
     * Arquiva (desativa + soft delete). Regra 12: grupo com ativos so e arquivado migrando-os para outro grupo ativo.
     *
     * @throws ValidationException
     */
    public function arquivarGrupo(AssetCategory $grupo, ?string $migrarParaId, string $justificativa, ?User $usuario = null): void
    {
        $this->exigirJustificativa($justificativa);

        $total = $this->ativosNoGrupo($grupo);

        if ($total > 0) {
            $destino = $migrarParaId
                ? AssetCategory::withoutGlobalScopes()->where('tenant_id', $grupo->tenant_id)->where('is_active', true)
                    ->whereNull('deleted_at')->whereKeyNot($grupo->id)->find($migrarParaId)
                : null;

            if (! $destino) {
                throw ValidationException::withMessages([
                    'migrar_para' => "O grupo tem {$total} ativo(s). Escolha outro grupo ativo para migra-los antes de arquivar.",
                ]);
            }
        }

        DB::transaction(function () use ($grupo, $total, $migrarParaId, $justificativa, $usuario) {
            if ($total > 0) {
                Asset::withoutGlobalScopes()->where('tenant_id', $grupo->tenant_id)->where('asset_category_id', $grupo->id)
                    ->whereNull('deleted_at')->update(['asset_category_id' => $migrarParaId]);
            }

            $grupo->forceFill(['is_active' => false])->save();
            $grupo->delete();

            $this->log($grupo, 'Catalogo: grupo arquivado', $usuario, [
                'justificativa' => $justificativa, 'ativos_migrados' => $total, 'migrado_para' => $migrarParaId,
            ]);
        });
    }

    public function restaurarGrupo(AssetCategory $grupo, ?User $usuario = null): void
    {
        $grupo->restore();
        $grupo->forceFill(['is_active' => true])->save();
        $this->log($grupo, 'Catalogo: grupo restaurado', $usuario);
    }

    /** Exclusao definitiva so de grupo que nunca foi usado por ativo. Retorna false se so puder arquivar. */
    public function purgarGrupo(AssetCategory $grupo, ?User $usuario = null): bool
    {
        if ($this->grupoTemHistorico($grupo)) {
            return false;
        }

        DB::transaction(function () use ($grupo, $usuario) {
            $this->log($grupo, 'Catalogo: grupo excluido definitivamente', $usuario, ['nome' => $grupo->name, 'slug' => $grupo->slug]);

            // os filhos tem FK cascade; apagados por baixo, sem historico a preservar
            $grupo->forceDelete();
        });

        return true;
    }

    /** Item da biblioteca: arquiva se ja foi referenciado por algum grupo; senao exclui de vez. Retorna 'arquivado'|'excluido'. */
    public function removerItem(CatalogoItem $item, ?string $justificativa = null, ?User $usuario = null): string
    {
        if ($this->itemTemHistorico($item)) {
            if ($this->itemUsadoEmImpeditivo($item)) {
                $this->exigirJustificativa((string) $justificativa);
            }

            $item->forceFill(['ativo' => false])->save();
            $item->delete();
            $this->log($item, 'Catalogo: item da biblioteca arquivado', $usuario, ['justificativa' => $justificativa]);

            return 'arquivado';
        }

        $this->log($item, 'Catalogo: item da biblioteca excluido definitivamente', $usuario, ['nome' => $item->nome, 'slug' => $item->slug]);
        $item->forceDelete();

        return 'excluido';
    }

    public function itemUsadoEmImpeditivo(CatalogoItem $item): bool
    {
        return CatalogoRequisito::withoutGlobalScopes()->where('item_id', $item->id)->where('ativo', true)
            ->whereIn('efeito', self::EFEITOS_IMPEDITIVOS)->exists();
    }

    // ------------------------------------------------------------------ edicao sensivel

    /**
     * Compara o estado do formulario do grupo com o banco e devolve as mudancas sensiveis:
     * IMP->ALR, remover/desativar/rebaixar documento/inspecao/impeditivo, ampliar intervalo de preventiva.
     *
     * @param  array<string, mixed>  $estado  estado do form (repeaters 'requisitos' e 'preventivas', chaves "record-{id}")
     * @return array<int, array{tipo: string, descricao: string, antes: mixed, depois: mixed}>
     */
    public function mudancasSensiveis(AssetCategory $grupo, array $estado): array
    {
        $mudancas = [];

        $linhas = fn (string $chave) => collect($estado[$chave] ?? [])
            ->mapWithKeys(fn ($linha, $k) => str_starts_with((string) $k, 'record-') ? [substr((string) $k, 7) => $linha] : []);

        $reqs = $linhas('requisitos');
        foreach (CatalogoRequisito::query()->where('grupo_id', $grupo->id)->with('item')->get() as $db) {
            $nome = $db->item?->nome ?? $db->slug;
            $linha = $reqs->get($db->id);
            $impeditivo = in_array($db->efeito, self::EFEITOS_IMPEDITIVOS, true);

            if ($linha === null) {
                if ($impeditivo && $db->ativo) {
                    $mudancas[] = $this->m('requisito_removido', "Requisito obrigatorio removido: {$nome} ({$db->efeito})", $db->efeito, null);
                }

                continue;
            }

            $novoEfeito = $linha['efeito'] ?? $db->efeito;
            if ($impeditivo && $novoEfeito !== $db->efeito) {
                $mudancas[] = $this->m('efeito_rebaixado', "{$nome}: {$db->efeito} -> {$novoEfeito}", $db->efeito, $novoEfeito);
            }

            if ($impeditivo && $db->ativo && ! filter_var($linha['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                $mudancas[] = $this->m('requisito_desativado', "Requisito obrigatorio desativado: {$nome} ({$db->efeito})", true, false);
            }
        }

        $preventivas = $linhas('preventivas');
        foreach (CatalogoPreventiva::query()->where('grupo_id', $grupo->id)->get() as $db) {
            $linha = $preventivas->get($db->id);

            if ($linha === null) {
                if ($db->ativo) {
                    $mudancas[] = $this->m('preventiva_removida', "Preventiva removida: {$db->nome}", $db->intervalo, null);
                }

                continue;
            }

            if ((int) ($linha['intervalo'] ?? $db->intervalo) > (int) $db->intervalo) {
                $mudancas[] = $this->m('intervalo_ampliado', "{$db->nome}: intervalo {$db->intervalo} -> {$linha['intervalo']}", $db->intervalo, (int) $linha['intervalo']);
            }

            if ($db->ativo && ! filter_var($linha['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                $mudancas[] = $this->m('preventiva_desativada', "Preventiva desativada: {$db->nome}", true, false);
            }
        }

        return $mudancas;
    }

    /** @throws ValidationException quando ha mudanca sensivel e a justificativa esta vazia. */
    public function exigirJustificativaSeSensivel(AssetCategory $grupo, array $estado, ?string $justificativa): array
    {
        $mudancas = $this->mudancasSensiveis($grupo, $estado);

        if ($mudancas !== []) {
            $this->exigirJustificativa((string) $justificativa, self::AVISO_RESPONSAVEL_TECNICO.' Informe a justificativa.');
        }

        return $mudancas;
    }

    public function registrarEdicaoSensivel(Model $alvo, array $mudancas, string $justificativa, ?User $usuario = null): void
    {
        $this->log($alvo, 'Catalogo: edicao sensivel', $usuario, [
            'justificativa' => $justificativa,
            'mudancas' => $mudancas,
            'aviso' => self::AVISO_RESPONSAVEL_TECNICO,
        ]);
    }

    // ------------------------------------------------------------------ interno

    public function log(Model $alvo, string $descricao, ?User $usuario = null, array $props = []): void
    {
        $usuario ??= auth()->user();

        $logger = activity()->performedOn($alvo)->withProperties($props);

        if ($usuario) {
            $logger->causedBy($usuario);
        }

        $logger->log($descricao);
    }

    private function m(string $tipo, string $descricao, mixed $antes, mixed $depois): array
    {
        return ['tipo' => $tipo, 'descricao' => $descricao, 'antes' => $antes, 'depois' => $depois];
    }

    private function exigirJustificativa(string $justificativa, string $msg = 'Informe a justificativa.'): void
    {
        if (mb_strlen(trim($justificativa)) < 5) {
            throw ValidationException::withMessages(['justificativa' => $msg]);
        }
    }

    private function slugLivre(string $tenantId, string $base): string
    {
        $slug = $base !== '' ? $base : 'grupo';
        $n = 1;

        while (AssetCategory::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}

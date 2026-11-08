<?php

namespace App\Services\Catalogo\Aptidao;

use App\Models\AssetCategory;
use App\Models\CatalogoAtributo;
use App\Models\CatalogoItem;
use App\Models\CatalogoPreventiva;
use App\Models\CatalogoRegra;
use App\Models\CatalogoRequisito;
use Illuminate\Support\Collection;

/**
 * Perfil EFETIVO de um grupo (so itens ativos e nao arquivados), lido do banco do tenant. O motor so enxerga isto:
 * o conteudo e dado, nao codigo. Tambem aceita um perfil "proposto" (estado do formulario) para a previa de impacto.
 */
class PerfilDoGrupo
{
    /**
     * @param  Collection<int, CatalogoAtributo>  $atributos
     * @param  Collection<int, CatalogoRequisito>  $requisitos
     * @param  Collection<int, CatalogoPreventiva>  $preventivas
     * @param  Collection<int, CatalogoRegra>  $regras
     */
    public function __construct(
        public AssetCategory $grupo,
        public Collection $atributos,
        public Collection $requisitos,
        public Collection $preventivas,
        public Collection $regras,
    ) {}

    public static function doGrupo(AssetCategory $grupo): self
    {
        $ativos = fn (string $classe) => $classe::withoutGlobalScopes()->where('tenant_id', $grupo->tenant_id)
            ->where('grupo_id', $grupo->id)->where('ativo', true)->whereNull('deleted_at')->orderBy('ordem')->get();

        $requisitos = $ativos(CatalogoRequisito::class);
        $requisitos->load(['item' => fn ($q) => $q->withoutGlobalScopes()]);

        return new self($grupo, $ativos(CatalogoAtributo::class), $requisitos, $ativos(CatalogoPreventiva::class), $ativos(CatalogoRegra::class));
    }

    /**
     * Aplica o estado do formulario (repeaters com chaves "record-{id}") sobre o perfil atual, SEM gravar nada:
     * serve a previa de impacto antes de salvar.
     *
     * @param  array<string, mixed>  $estado
     */
    public function comEstadoDoForm(array $estado): self
    {
        $copia = clone $this;
        $copia->requisitos = $this->aplicar($this->requisitos, $estado['requisitos'] ?? null, CatalogoRequisito::class,
            ['item_id', 'efeito', 'validade_dias', 'gera_os', 'norma', 'ativo']);
        $copia->preventivas = $this->aplicar($this->preventivas, $estado['preventivas'] ?? null, CatalogoPreventiva::class,
            ['nome', 'medidor', 'intervalo', 'tolerancia_pct', 'ou_dias', 'regra_ou', 'ativo']);

        foreach ($copia->requisitos as $r) {
            if (! $r->relationLoaded('item') || ! $r->item) {
                $r->setRelation('item', CatalogoItem::withoutGlobalScopes()->find($r->item_id));
            }
        }

        return $copia;
    }

    private function aplicar(Collection $atuais, ?array $linhas, string $classe, array $campos): Collection
    {
        if ($linhas === null) {
            return $atuais;
        }

        $porId = $atuais->keyBy('id');
        $resultado = collect();

        foreach ($linhas as $chave => $linha) {
            $id = str_starts_with((string) $chave, 'record-') ? substr((string) $chave, 7) : null;
            $modelo = $id && $porId->has($id) ? clone $porId->get($id) : new $classe(['slug' => 'novo-'.$chave, 'grupo_id' => $this->grupo->id, 'tenant_id' => $this->grupo->tenant_id]);

            foreach ($campos as $campo) {
                if (array_key_exists($campo, $linha)) {
                    $modelo->setAttribute($campo, $linha[$campo] === '' ? null : $linha[$campo]);
                }
            }

            if (filter_var($modelo->ativo ?? true, FILTER_VALIDATE_BOOLEAN)) {
                $resultado->push($modelo);
            }
        }

        return $resultado;
    }
}

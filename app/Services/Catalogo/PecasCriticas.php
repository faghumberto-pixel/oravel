<?php

namespace App\Services\Catalogo;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoPecaCritica;
use App\Models\Material;
use App\Services\Catalogo\Aptidao\AptidaoGate;
use Illuminate\Support\Collection;

/**
 * G1 fase 7: pecas criticas do grupo x estoque. O vinculo e com Material (e o que a OS consome), nunca com Part.
 * Estoque = Material.current_stock (soma das filiais, mantido pelo sistema) comparado ao minimo do proprio Material.
 * Nao mexe na aptidao do ativo: e um alerta de abastecimento para a manutencao nao parar esperando peca.
 */
class PecasCriticas
{
    public const SEM_VINCULO = 'sem_vinculo';

    public const SEM_ESTOQUE = 'sem_estoque';

    public const ABAIXO_MINIMO = 'abaixo_minimo';

    public const OK = 'ok';

    public const ROTULOS = [
        self::SEM_ESTOQUE => 'Sem estoque',
        self::ABAIXO_MINIMO => 'Abaixo do mínimo',
        self::SEM_VINCULO => 'Sem material vinculado',
        self::OK => 'Estoque ok',
    ];

    /**
     * Situacao de cada peca critica ativa do tenant (so se a chave do catalogo estiver ligada), da mais grave para a mais leve.
     *
     * @return Collection<int, array{grupo: AssetCategory, peca: CatalogoPecaCritica, material: ?Material, estoque: ?float, minimo: ?float, estado: string, ativos: int}>
     */
    public static function situacao(string $tenantId): Collection
    {
        if (! AptidaoGate::ligada($tenantId)) {
            return collect();
        }

        $pecas = CatalogoPecaCritica::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('ativo', true)->orderBy('ordem')->get();
        $grupos = AssetCategory::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_active', true)->whereIn('id', $pecas->pluck('grupo_id'))->get()->keyBy('id');
        $materiais = Material::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', $pecas->pluck('material_id')->filter())->get()->keyBy('id');
        $ativos = Asset::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('asset_category_id', $grupos->keys())
            ->selectRaw('asset_category_id, count(*) as total')->groupBy('asset_category_id')->pluck('total', 'asset_category_id');

        return $pecas->filter(fn ($p) => $grupos->has($p->grupo_id))->map(function (CatalogoPecaCritica $p) use ($grupos, $materiais, $ativos) {
            $material = $p->material_id ? $materiais->get($p->material_id) : null;
            $estoque = $material ? (float) $material->current_stock : null;
            $minimo = $material ? (float) $material->min_stock : null;

            return [
                'grupo' => $grupos[$p->grupo_id], 'peca' => $p, 'material' => $material, 'estoque' => $estoque, 'minimo' => $minimo,
                'estado' => self::estado($material), 'ativos' => (int) ($ativos[$p->grupo_id] ?? 0),
            ];
        })->sortBy(fn (array $l) => [array_search($l['estado'], [self::SEM_ESTOQUE, self::ABAIXO_MINIMO, self::SEM_VINCULO, self::OK], true), -$l['ativos'], $l['grupo']->name])->values();
    }

    public static function estado(?Material $material): string
    {
        if (! $material) {
            return self::SEM_VINCULO;
        }

        if ((float) $material->current_stock <= 0) {
            return self::SEM_ESTOQUE;
        }

        return (float) $material->min_stock > 0 && (float) $material->current_stock <= (float) $material->min_stock ? self::ABAIXO_MINIMO : self::OK;
    }

    /** @return array<string, int> estado => quantidade de pecas */
    public static function contagem(string $tenantId): array
    {
        $c = array_fill_keys(array_keys(self::ROTULOS), 0);

        foreach (self::situacao($tenantId) as $l) {
            $c[$l['estado']]++;
        }

        return $c;
    }
}

<?php

namespace App\Services\Frota;

use App\Models\Part;
use App\Models\PartCategory;

/** Categorias e itens-padrão de estoque da frota (cria só o que falta; nunca altera o que o cliente já tem). */
class CatalogoEstoqueFrota
{
    /** @var array<string, array<int, array{0: string, 1: string, 2: int}>> categoria => [[nome, unidade, mínimo]] */
    public const ITENS = [
        'Peças de Manutenção e Reposição' => [
            ['Filtro de óleo', 'UN', 4], ['Filtro de combustível', 'UN', 4], ['Filtro de ar', 'UN', 2],
            ['Pastilha de freio', 'JG', 2], ['Lona de freio', 'JG', 2], ['Correia', 'UN', 2], ['Lâmpada', 'UN', 10], ['Fusível', 'UN', 20],
        ],
        'Insumos e Fluidos Operacionais' => [
            ['Óleo de motor', 'LT', 40], ['Óleo hidráulico', 'LT', 20], ['Fluido de freio', 'LT', 5],
            ['Aditivo de radiador', 'LT', 10], ['Arla 32', 'LT', 100], ['Graxa', 'KG', 5],
        ],
        'Pneus e Borracharia' => [
            ['Pneu', 'UN', 2], ['Bateria', 'UN', 1], ['Câmara de ar', 'UN', 2], ['Kit de reparo rápido', 'UN', 5],
        ],
        'Equipamentos de Segurança e Acessórios' => [
            ['Triângulo de sinalização', 'UN', 5], ['Cone de sinalização', 'UN', 10], ['Extintor', 'UN', 3],
            ['Cinta de amarração', 'UN', 10], ['Catraca', 'UN', 5], ['Lona', 'UN', 3], ['Kit de primeiros socorros', 'UN', 3],
            ['Capacete', 'UN', 5], ['Luva', 'PC', 10], ['Colete refletivo', 'UN', 10], ['Óculos de proteção', 'UN', 10],
        ],
    ];

    /** @return array{categorias: int, itens: int} quantos foram criados agora. */
    public function garantir(string $tenantId): array
    {
        $novas = 0;
        $itens = 0;

        foreach (self::ITENS as $nomeCategoria => $lista) {
            $categoria = PartCategory::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenantId, 'name' => $nomeCategoria], ['slug' => str($nomeCategoria)->slug()]);
            $novas += $categoria->wasRecentlyCreated ? 1 : 0;

            foreach ($lista as [$nome, $unidade, $minimo]) {
                $sku = 'FROTA-'.strtoupper(str($nome)->slug()->limit(24, '')->toString());
                $peca = Part::withoutGlobalScopes()->firstOrCreate(
                    ['tenant_id' => $tenantId, 'sku' => $sku],
                    ['part_category_id' => $categoria->id, 'name' => $nome, 'unit_of_measure' => $unidade, 'minimum_stock' => $minimo, 'cost_price' => 0, 'is_active' => true]
                );
                $itens += $peca->wasRecentlyCreated ? 1 : 0;
            }
        }

        return ['categorias' => $novas, 'itens' => $itens];
    }
}

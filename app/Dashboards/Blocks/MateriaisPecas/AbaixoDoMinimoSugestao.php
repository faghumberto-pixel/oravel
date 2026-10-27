<?php

namespace App\Dashboards\Blocks\MateriaisPecas;

use App\Dashboards\Payload;
use App\Models\Material;

class AbaixoDoMinimoSugestao extends MateriaisBlock
{
    public static function key(): string
    {
        return 'materiais.abaixo_minimo_lista';
    }

    public static function title(): string
    {
        return 'Abaixo do mínimo';
    }

    public static function subtitle(): string
    {
        return 'Sugestão de compra até o estoque máximo';
    }

    public static function size(): int
    {
        return 8;
    }

    public static function defaultConfig(): array
    {
        return ['limite' => 8];
    }

    public function data(array $config): array
    {
        $itens = Material::query()
            ->with('category:id,name')
            ->where('min_stock', '>', 0)
            ->whereColumn('current_stock', '<', 'min_stock')
            ->get(['id', 'name', 'material_category_id', 'current_stock', 'min_stock', 'max_stock'])
            ->sortBy(fn ($m) => $m->current_stock / $m->min_stock)
            ->take((int) ($config['limite'] ?? 8));

        $linhas = $itens->map(function ($m) {
            $alvo = $m->max_stock > 0 ? $m->max_stock : $m->min_stock * 2;

            return [
                $m->name,
                $m->category?->name ?? '—',
                (string) $m->current_stock,
                (string) $m->min_stock,
                (string) max(0, $alvo - $m->current_stock),
                match (true) {
                    $m->current_stock <= 0 => Payload::pill('crit', 'Zerado'),
                    $m->current_stock < $m->min_stock / 2 => Payload::pill('crit', 'Crítico'),
                    default => Payload::pill('warn', 'Baixo'),
                },
            ];
        })->values()->all();

        return Payload::table(['Item', 'Categoria', '>Saldo', '>Mínimo', '>Sugerido', 'Situação'], $linhas, 'Nenhum item abaixo do mínimo.');
    }
}

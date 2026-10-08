<?php

namespace App\Support;

/**
 * Módulos de MENU (config/menu_modules.php): menus pai e páginas que não têm
 * tabela própria, mas precisam de uma chave no contrato para a Central poder
 * liberar ou bloquear o menu.
 */
class MenuModules
{
    /**
     * @return array<string, array{label: string, grupo: string, menu: string, slug: string, herda: array<int, string>}>
     */
    public static function all(): array
    {
        return config('menu_modules', []);
    }

    /** Chave do módulo de um item de menu (grupo + rótulo), ou null se o item é coberto por outro módulo. */
    public static function keyForItem(?string $grupo, ?string $rotulo): ?string
    {
        foreach (static::all() as $key => $m) {
            if ($m['menu'] === $rotulo && $m['grupo'] === $grupo) {
                return $key;
            }
        }

        return null;
    }

    /** Chave do módulo de uma página pelo slug da rota (/admin/{slug}). */
    public static function keyForSlug(?string $slug): ?string
    {
        foreach (static::all() as $key => $m) {
            if ($m['slug'] === $slug) {
                return $key;
            }
        }

        return null;
    }

    /** @return array<string, string> chave => rótulo */
    public static function options(): array
    {
        return array_map(fn ($m) => $m['label'], static::all());
    }
}

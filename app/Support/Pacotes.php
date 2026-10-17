<?php

namespace App\Support;

use App\Filament\Central\Resources\ContratoResource;
use App\Models\Tenant;

/**
 * Pacotes de contrato (Locadora, CRM, Estoque, Logística): quais módulos cada um liga, nome para exibir e a
 * tela inicial de quem entra no painel. A fonte é config/pacotes.php; as chaves vêm dos grupos de menu reais.
 */
class Pacotes
{
    /** @return array<string, array{nome: string, descricao: string, grupos: array|string, features: array, inicio: ?string}> */
    public static function todos(): array
    {
        return config('pacotes.pacotes', []);
    }

    /** @return array<string, string> código => nome (para selects, filtros e badges) */
    public static function opcoes(): array
    {
        return array_map(fn ($p) => $p['nome'], self::todos());
    }

    public static function nome(?string $codigo): string
    {
        return $codigo ? (self::opcoes()[$codigo] ?? $codigo) : 'Personalizado';
    }

    /**
     * Chaves de módulo do pacote: base comum + grupos de menu do pacote + extras.
     *
     * @return list<string>
     */
    public static function features(string $codigo): array
    {
        $pacote = self::todos()[$codigo] ?? null;

        if (! $pacote) {
            return [];
        }

        $porGrupo = ContratoResource::groupedFeatureOptions();

        if ($pacote['grupos'] === '*') {
            // Tudo que os menus oferecem + os extras explícitos de qualquer pacote (ex.: módulo recém-criado).
            return collect($porGrupo)->flatMap(fn ($opcoes) => array_keys($opcoes))
                ->merge(collect(self::todos())->flatMap(fn ($p) => $p['features'] ?? []))
                ->merge(config('pacotes.base.features', []))
                ->unique()->values()->all();
        }

        $base = config('pacotes.base', []);
        $grupos = array_merge($base['grupos'] ?? [], (array) $pacote['grupos']);

        return collect($grupos)
            ->flatMap(fn ($g) => array_keys($porGrupo[$g] ?? []))
            ->merge($base['features'] ?? [])
            ->merge($pacote['features'] ?? [])
            ->unique()->values()->all();
    }

    /** Chaves do pacote que pertencem a um grupo de menu (para preencher cada CheckboxList do contrato). */
    public static function featuresDoGrupo(string $codigo, string $grupo): array
    {
        $doGrupo = array_keys(ContratoResource::groupedFeatureOptions()[$grupo] ?? []);

        return array_values(array_intersect(self::features($codigo), $doGrupo));
    }

    /** Pacote do contrato do tenant (nulo = personalizado). */
    public static function doTenant(?Tenant $tenant): ?string
    {
        return $tenant?->plan?->pacote;
    }

    /**
     * Tela em que o usuário entra: a do pacote (CRM → Funil, Estoque → Almoxarifado, Logística → Programação) quando o
     * pacote tem uma e o usuário pode acessá-la; senão null (painel inicial de sempre).
     */
    public static function urlInicial(?Tenant $tenant): ?string
    {
        $pagina = self::todos()[self::doTenant($tenant)]['inicio'] ?? null;

        if (! $pagina || ! class_exists($pagina) || ! $pagina::canAccess()) {
            return null;
        }

        try {
            return $pagina::getUrl();
        } catch (\Throwable) {
            return null;
        }
    }
}

<?php

namespace App\Support;

use App\Filament\Central\Resources\ContratoResource;
use App\Models\Plan;
use App\Models\Tenant;

/**
 * Pacotes de contrato (Locadora, Vendas, Manutenção, Serviços, Estoque, Logística): quais módulos cada um liga, nome para exibir e a
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

    /** Converte códigos antigos (ex.: "crm") para o atual ("vendas"). */
    public static function normalizar(?string $codigo): ?string
    {
        return $codigo ? (config('pacotes.aliases', [])[$codigo] ?? $codigo) : null;
    }

    public static function nome(?string $codigo): string
    {
        $codigo = self::normalizar($codigo);

        return $codigo ? (self::opcoes()[$codigo] ?? $codigo) : 'Personalizado';
    }

    /**
     * Chaves de módulo do pacote: base comum + grupos de menu do pacote + extras.
     *
     * @return list<string>
     */
    public static function features(string $codigo): array
    {
        $pacote = self::todos()[self::normalizar($codigo)] ?? null;

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
            ->reject(fn ($chave) => in_array($chave, $pacote['excluir'] ?? [], true))
            ->unique()->values()->all();
    }

    /** Chaves do pacote que pertencem a um grupo de menu (para preencher cada CheckboxList do contrato). */
    public static function featuresDoGrupo(string $codigo, string $grupo): array
    {
        $doGrupo = array_keys(ContratoResource::groupedFeatureOptions()[$grupo] ?? []);

        return array_values(array_intersect(self::features($codigo), $doGrupo));
    }

    /** Valor mensal sugerido do pacote (R$) ou null quando ainda não foi definido. */
    public static function precoSugerido(?string $codigo): ?float
    {
        $preco = self::todos()[self::normalizar($codigo)]['preco_base'] ?? null;

        return $preco === null ? null : (float) $preco;
    }

    public static function cicloSugerido(?string $codigo): string
    {
        return self::todos()[self::normalizar($codigo)]['ciclo'] ?? 'monthly';
    }

    public const TAG_VITRINE = 'vitrine';

    /**
     * Plano de vitrine do pacote: o `Plan` que o site/checkout vende (um por pacote, identificado por pacote + tag).
     * Idempotente. Sem preço definido no config o plano fica INATIVO: o checkout não vende pacote a R$ 0.
     */
    public static function planoDeVitrine(string $codigo): Plan
    {
        $codigo = self::normalizar($codigo);
        $pacote = self::todos()[$codigo] ?? throw new \InvalidArgumentException("Pacote desconhecido: {$codigo}");
        $preco = self::precoSugerido($codigo);

        $plano = Plan::where('pacote', $codigo)->where('campaign_tag', self::TAG_VITRINE)->first() ?? new Plan;
        $plano->forceFill([
            'name' => 'Oravel '.$pacote['nome'],
            'pacote' => $codigo,
            'campaign_tag' => self::TAG_VITRINE,
            'level' => $plano->level ?? 1,
            'price' => $preco ?? 0,
            'base_price' => $preco ?? 0,
            'billing_cycle' => self::cicloSugerido($codigo),
            'features' => array_fill_keys(self::features($codigo), true),
            'is_active' => $preco !== null && $preco > 0,
        ])->save();

        return $plano;
    }

    /** Pacote do contrato do tenant (nulo = personalizado). */
    public static function doTenant(?Tenant $tenant): ?string
    {
        return self::normalizar($tenant?->plan?->pacote);
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

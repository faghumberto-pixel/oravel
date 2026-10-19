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
            $complementos = (array) config('pacotes.grupos_complementos', []);

            return collect($porGrupo)->except($complementos)->flatMap(fn ($opcoes) => array_keys($opcoes))
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

    public const TAG_VITRINE = 'vitrine';

    /** Contrato criado no cadastro quando o cliente escolhe mais de um produto: um por empresa, nunca aparece no site. */
    public const TAG_COMBO = 'combo';

    /** Plano de vitrine já criado para o pacote (o que o site/checkout vende), ou null. */
    public static function vitrine(?string $codigo): ?Plan
    {
        $codigo = self::normalizar($codigo);

        return $codigo ? Plan::where('pacote', $codigo)->where('campaign_tag', self::TAG_VITRINE)->first() : null;
    }

    /** Valor mensal do pacote = o cadastrado na Central no plano de vitrine (null enquanto não definido). */
    public static function precoSugerido(?string $codigo): ?float
    {
        $preco = (float) (self::vitrine($codigo)?->base_price ?? 0);

        return $preco > 0 ? $preco : null;
    }

    public static function cicloSugerido(?string $codigo): string
    {
        return self::vitrine($codigo)?->billing_cycle ?: 'monthly';
    }

    /**
     * Cria o plano de vitrine do pacote (um por pacote, identificado por pacote + tag). Preço, ciclo e se está à
     * venda (is_active) são cadastrados na Central e NUNCA sobrescritos aqui: nasce inativo e sem preço, e o checkout
     * só vende plano ativo. Os módulos só são ressincronizados com $sincronizarModulos (preserva ajustes da Central).
     */
    public static function planoDeVitrine(string $codigo, bool $sincronizarModulos = false): Plan
    {
        $codigo = self::normalizar($codigo);
        $pacote = self::todos()[$codigo] ?? throw new \InvalidArgumentException("Pacote desconhecido: {$codigo}");

        $plano = self::vitrine($codigo);

        if (! $plano) {
            return Plan::create([
                'name' => 'Oravel '.$pacote['nome'],
                'pacote' => $codigo,
                'campaign_tag' => self::TAG_VITRINE,
                'level' => 1,
                'price' => 0,
                'base_price' => 0,
                'billing_cycle' => 'monthly',
                'features' => array_fill_keys(self::features($codigo), true),
                'is_active' => false,
                'implantacao_opcional' => true,
            ]);
        }

        if ($sincronizarModulos) {
            $plano->update(['features' => array_fill_keys(self::features($codigo), true)]);
        }

        return $plano;
    }

    /**
     * Produtos contratados no contrato: a lista `pacotes` quando existe; senão o pacote principal; contrato personalizado (sem
     * pacote) segue como Locadora, que é o que todas as empresas antigas são. Códigos desconhecidos são ignorados.
     *
     * @return list<string>
     */
    public static function produtosDoPlano(?Plan $plano): array
    {
        $codigos = collect((array) ($plano?->pacotes ?? []))->push($plano?->pacote)
            ->map(fn ($c) => self::normalizar(is_string($c) ? $c : null))
            ->filter(fn ($c) => $c && isset(self::todos()[$c]))
            ->unique()->values()->all();

        return $codigos ?: ['locadora'];
    }

    /** @return list<string> */
    public static function produtosDoTenant(?Tenant $tenant): array
    {
        return self::produtosDoPlano($tenant?->plan);
    }

    /**
     * Módulos de vários produtos juntos (união sem repetir). Um módulo excluído por um produto só sai se TODOS os
     * produtos o excluem: ele entra assim que algum produto escolhido o oferece.
     *
     * @param  list<string>  $codigos
     * @return list<string>
     */
    public static function featuresDeProdutos(array $codigos): array
    {
        return collect($codigos)->flatMap(fn ($c) => self::features($c))->unique()->values()->all();
    }

    /**
     * Cores do sistema por produto: o menu, o topo e o destaque do painel (desktop e app) ganham a cor do único produto contratado
     * (config/pacotes.php, `app.cor`). Locadora (e empresa com mais de um produto ou contrato personalizado) fica no visual padrão de sempre.
     *
     * @return array{codigo: string, cor: string, topo: string, menu: string, menu_fim: string}|null
     */
    public static function tema(?Tenant $tenant): ?array
    {
        $produtos = self::produtosDoTenant($tenant);

        if (count($produtos) !== 1 || $produtos[0] === 'locadora') {
            return null;
        }

        $cor = self::todos()[$produtos[0]]['app']['cor'] ?? null;

        if (! is_string($cor) || ! preg_match('/^#[0-9a-f]{6}$/i', $cor)) {
            return null;
        }

        return ['codigo' => $produtos[0], 'cor' => $cor, 'topo' => self::escurecer($cor, 0.12), 'menu' => self::escurecer($cor, 0.5), 'menu_fim' => self::escurecer($cor, 0.62)];
    }

    /** Mistura a cor com preto (0 = igual, 1 = preto). */
    public static function escurecer(string $hex, float $quanto): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $f = 1 - max(0, min(1, $quanto));

        return sprintf('#%02x%02x%02x', (int) round($r * $f), (int) round($g * $f), (int) round($b * $f));
    }

    /** Plano com preço cheio e escolha de forma de pagamento (vitrine do site ou combinação de produtos). */
    public static function ehVitrine(?Plan $plano): bool
    {
        return in_array($plano?->campaign_tag, [self::TAG_VITRINE, self::TAG_COMBO], true);
    }

    /**
     * Produtos à venda no site (vitrine ativa e com preço), exceto os códigos de $excluir.
     *
     * @param  list<string>  $excluir
     * @return array<string, Plan> código => plano de vitrine
     */
    public static function vendaveis(array $excluir = []): array
    {
        return collect(self::todos())->keys()->reject(fn ($c) => in_array($c, $excluir, true))
            ->mapWithKeys(fn ($c) => [$c => self::vitrine($c)])
            ->filter(fn ($p) => $p && $p->is_active && (float) $p->base_price > 0)->all();
    }

    /**
     * Contrato da empresa que escolheu vários produtos: valor cheio = SOMA dos valores cheios das vitrines (o desconto de
     * recorrência é aplicado depois, sobre a soma, em Precos); módulos = união; implantação = soma (só se o principal tem).
     *
     * @param  list<string>  $extras
     */
    public static function planoCombinado(Plan $principal, array $extras): Plan
    {
        $vitrines = [$principal->pacote => $principal];
        foreach ($extras as $codigo) {
            $v = self::vendaveis()[self::normalizar($codigo)] ?? throw new \InvalidArgumentException("Produto indisponível: {$codigo}");
            $vitrines[$v->pacote] = $v;
        }

        $codigos = array_keys($vitrines);
        $soma = round(collect($vitrines)->sum(fn (Plan $p) => (float) $p->base_price), 2);
        $implantacao = (float) $principal->implementation_fee > 0 ? round(collect($vitrines)->sum(fn (Plan $p) => (float) $p->implementation_fee), 2) : 0;

        return Plan::create([
            'name' => 'Oravel '.collect($codigos)->map(fn ($c) => self::nome($c))->implode(' + '),
            'pacote' => $principal->pacote,
            'pacotes' => $codigos,
            'campaign_tag' => self::TAG_COMBO,
            'level' => 1,
            'price' => $soma,
            'base_price' => $soma,
            'billing_cycle' => $principal->billing_cycle,
            'implementation_fee' => $implantacao,
            'implementation_installments' => max(1, (int) collect($vitrines)->max('implementation_installments')),
            'implantacao_opcional' => true,
            'features' => array_fill_keys(self::featuresDeProdutos($codigos), true),
            'is_active' => true,
        ]);
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

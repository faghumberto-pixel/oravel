<?php

namespace App\Dashboards;

use App\Filament\Pages\VisaoGeralAtivos;
use App\Filament\Pages\VisaoGeralComercial;
use App\Filament\Pages\VisaoGeralCompras;
use App\Filament\Pages\VisaoGeralFinanceiro;
use App\Filament\Pages\VisaoGeralFrota;
use App\Filament\Pages\VisaoGeralManutencao;
use App\Filament\Pages\VisaoGeralMateriais;
use App\Filament\Pages\VisaoGeralPlanejamento;
use App\Filament\Pages\VisaoGeralRh;
use App\Filament\Pages\VisaoGeralVendas;
use App\Models\User;
use App\Support\PainelDaArea;
use App\Support\Tenancy;

/**
 * "Painel de Gestão": a visão da empresa inteira. Junta os principais indicadores de cada área (comercial, financeiro, manutenção, ativos e frota,
 * materiais e compras, pessoas, planejamento) usando os mesmos blocos dos dashboards por área. Cada bloco só entra se o contrato cobre a área e
 * o usuário pode ler (Block::visibleTo), então a mesma tela serve a qualquer empresa: mostra só o que ela contratou.
 *
 * Cada área tem uma lista de candidatos a destaque (números) e a gráficos; entram os primeiros visíveis. O primeiro destaque de cada área sobe
 * para a faixa "Resumo da empresa", no topo.
 */
class VisaoEmpresa
{
    /**
     * @return list<array{key: string, label: string, icon: string, tom: string, link: ?string, kpis: list<string>, graficos: list<string>}>
     */
    public static function definicao(): array
    {
        return [
            [
                'key' => 'comercial', 'label' => 'Comercial e vendas', 'icon' => 'heroicon-o-briefcase', 'tom' => '#db2777',
                'paginas' => ['vendas' => VisaoGeralVendas::class, 'comercial' => VisaoGeralComercial::class],
                'kpis' => ['comercial.pipeline', 'vendas.meta_mes', 'vendas.propostas_aberto', 'vendas.ciclo_venda', 'comercial.contratos_ativos', 'comercial.contratos_vencendo', 'comercial.conversao_orcamentos'],
                'graficos' => ['comercial.funil', 'comercial.vendas_por_vendedor', 'vendas.perdas_motivo', 'comercial.orcamentos_situacao', 'comercial.contratos_por_mes'],
            ],
            [
                'key' => 'financeiro', 'label' => 'Financeiro', 'icon' => 'heroicon-o-banknotes', 'tom' => '#059669',
                'paginas' => ['financeiro' => VisaoGeralFinanceiro::class],
                'kpis' => ['financeiro.saldo_mes', 'financeiro.a_receber_mes', 'financeiro.recebido_mes', 'financeiro.a_pagar_mes', 'financeiro.pago_mes'],
                'graficos' => ['financeiro.recebido_x_pago', 'financeiro.receita_por_categoria', 'financeiro.recebimentos_atrasados'],
            ],
            [
                'key' => 'manutencao', 'label' => 'Manutenção', 'icon' => 'heroicon-o-wrench-screwdriver', 'tom' => '#d97706',
                'paginas' => ['manutencao' => VisaoGeralManutencao::class],
                'kpis' => ['manutencao.atendimentos_hoje', 'manutencao.equipamentos_parados', 'manutencao.no_prazo', 'manutencao.custo_medio'],
                'graficos' => ['manutencao.por_situacao', 'manutencao.custo_por_mes', 'manutencao.defeitos_frequentes'],
            ],
            [
                'key' => 'ativos', 'label' => 'Ativos e frota', 'icon' => 'heroicon-o-truck', 'tom' => '#0284c7',
                'paginas' => ['ativos' => VisaoGeralAtivos::class, 'frota' => VisaoGeralFrota::class],
                'kpis' => ['ativos.taxa_locacao', 'ativos.total', 'ativos.disponiveis', 'ativos.em_manutencao', 'frota.combustivel_mes', 'frota.multas_em_aberto'],
                'graficos' => ['ativos.por_situacao', 'ativos.por_categoria', 'ativos.por_cliente', 'frota.combustivel_por_mes'],
            ],
            [
                'key' => 'materiais', 'label' => 'Materiais e compras', 'icon' => 'heroicon-o-archive-box', 'tom' => '#7c3aed',
                'paginas' => ['materiais' => VisaoGeralMateriais::class, 'compras' => VisaoGeralCompras::class],
                'kpis' => ['materiais.valor_em_estoque', 'materiais.abaixo_minimo_total', 'materiais.compras_a_receber', 'compras.pedidos_atrasados'],
                'graficos' => ['materiais.estoque_por_categoria', 'compras.por_mes', 'materiais.mais_consumidos'],
            ],
            [
                'key' => 'pessoas', 'label' => 'Pessoas', 'icon' => 'heroicon-o-user-group', 'tom' => '#e11d48',
                'paginas' => ['rh' => VisaoGeralRh::class],
                'kpis' => ['rh.colaboradores_ativos', 'rh.ponto_hoje'],
                'graficos' => ['rh.por_situacao', 'rh.admissoes_por_mes', 'rh.certificacoes_a_vencer'],
            ],
            [
                'key' => 'planejamento', 'label' => 'Planejamento de manutenção', 'icon' => 'heroicon-o-calendar-days', 'tom' => '#4f46e5',
                'paginas' => ['planejamento' => VisaoGeralPlanejamento::class],
                'kpis' => ['pmp.preventivas_vencidas', 'pmp.cobertura'],
                'graficos' => ['manutencao.carga_tecnico', 'pmp.mais_atrasadas', 'pmp.a_vencer'],
            ],
        ];
    }

    /** A visão da empresa está liberada para esta empresa? (mesma regra dos dashboards por área) */
    public static function disponivel(?User $user): bool
    {
        return $user && PainelDaArea::liberadoPara(Tenancy::current()) && self::resumo($user)['secoes'] !== [];
    }

    /**
     * @return array{destaques: list<array>, secoes: list<array>}
     */
    public static function resumo(?User $user): array
    {
        $tenant = Tenancy::current();
        if (! $user || ! $tenant) {
            return ['destaques' => [], 'secoes' => []];
        }

        $destaques = [];
        $secoes = [];

        // O resumo (um número por área, no topo) só faz sentido com duas ou mais áreas; com uma só, o número fica na própria seção.
        $comResumo = collect(self::definicao())->filter(fn ($def) => self::blocos($def['kpis'], $user, $tenant, 1) !== [])->count() >= 2;

        foreach (self::definicao() as $def) {
            $kpis = self::blocos($def['kpis'], $user, $tenant, 4);
            $todos = self::blocos($def['graficos'], $user, $tenant, 6);
            // Gráficos compactos dividem a linha (até 3); tabelas, listas, mapas e calor precisam de linha própria, mais larga.
            $graficos = array_slice(array_values(array_filter($todos, fn ($b) => ! in_array($b['payload']['type'], self::LARGOS, true))), 0, 3);
            $listas = array_slice(array_values(array_filter($todos, fn ($b) => in_array($b['payload']['type'], self::LARGOS, true))), 0, 2);
            if (! $kpis && ! $graficos && ! $listas) {
                continue;
            }

            // O 1º número da área vira o destaque do resumo, no topo; o resto fica na seção.
            if ($kpis && $comResumo) {
                $destaques[] = ['label' => $def['label'], 'tom' => $def['tom'], 'icon' => $def['icon'], 'bloco' => array_shift($kpis)];
            }

            // Um número sozinho não ocupa uma linha (sobraria buraco): entra na linha dos gráficos quando cabe (até 2 gráficos);
            // senão fica só no dashboard completo da área ("Ver tudo").
            if (count($kpis) === 1 && count($graficos) > 2) {
                $kpis = [];
            }
            if (! $kpis && ! $graficos && ! $listas) {
                continue;
            }

            // Poucos blocos no total (até 3): dividem uma linha só, em vez de um número sozinho e o resto da linha vazio.
            $juntos = $kpis && $graficos && count($kpis) + count($graficos) <= 3;
            $secoes[] = [
                'key' => $def['key'], 'label' => $def['label'], 'icon' => $def['icon'], 'tom' => $def['tom'],
                'link' => self::link($def['paginas'], $user),
                'kpis' => $juntos ? [] : self::comLargura($kpis, [1 => 4, 2 => 6, 3 => 4]),
                'graficos' => $juntos
                    ? self::comLargura(array_merge($kpis, $graficos), [2 => 6, 3 => 4])
                    : self::comLargura($graficos, [1 => 6, 2 => 6, 3 => 4]),
                'listas' => self::comLargura($listas, [1 => 12, 2 => 6]),
            ];
        }

        return ['destaques' => self::distribuir($destaques), 'secoes' => $secoes];
    }

    /** Tipos de bloco que precisam de largura (tabela, lista, mapa, mapa de calor): nunca dividem a linha com mais dois. */
    private const LARGOS = ['table', 'timeline', 'alerts', 'map', 'heat'];

    /**
     * Linhas equilibradas para o resumo (7 cartões viram 4 + 3, não 4 + 1 buraco); cada cartão ocupa a largura inteira da sua linha.
     *
     * @param  list<array>  $cartoes
     * @return list<array>
     */
    private static function distribuir(array $cartoes): array
    {
        $n = count($cartoes);
        $linhas = match (true) {
            $n <= 4 => [$n],
            $n === 5 => [3, 2],
            $n === 6 => [3, 3],
            $n === 7 => [4, 3],
            default => array_merge(array_fill(0, intdiv($n, 4), 4), $n % 4 ? [$n % 4] : []),
        };

        $i = 0;
        foreach ($linhas as $quantos) {
            for ($k = 0; $k < $quantos; $k++, $i++) {
                $cartoes[$i]['span'] = intdiv(12, max(1, $quantos));
            }
        }

        return $cartoes;
    }

    /**
     * Os primeiros $max blocos visíveis da lista, já calculados.
     *
     * @param  list<string>  $chaves
     * @return list<array{key: string, title: string, subtitle: string, size: int, payload: array}>
     */
    private static function blocos(array $chaves, User $user, $tenant, int $max): array
    {
        $blocos = [];
        foreach ($chaves as $chave) {
            if (count($blocos) >= $max) {
                break;
            }
            $classe = BlockRegistry::find($chave);
            if (! $classe || ! $classe::visibleTo($user)) {
                continue;
            }
            $blocos[] = [
                'key' => $chave,
                'title' => $classe::title(),
                'subtitle' => $classe::subtitle(),
                'size' => $classe::size(),
                'payload' => DashboardRenderer::payload($classe, $classe::defaultConfig(), $tenant, $user),
            ];
        }

        return $blocos;
    }

    /**
     * Largura de cada bloco conforme quantos entraram na linha, para a grade sempre fechar as 12 colunas.
     *
     * @param  list<array>  $blocos
     * @param  array<int, int>  $porQuantidade
     * @return list<array>
     */
    private static function comLargura(array $blocos, array $porQuantidade): array
    {
        $span = $porQuantidade[count($blocos)] ?? 4;

        return array_map(fn ($b) => ['size' => $span] + $b, $blocos);
    }

    /** Página do dashboard completo da área (a primeira a que o usuário tem acesso), para o "ver tudo". */
    private static function link(array $paginas, User $user): ?string
    {
        foreach ($paginas as $dashboard => $pagina) {
            if (DashboardRenderer::available($dashboard, $user)) {
                return $pagina::getUrl();
            }
        }

        return null;
    }
}

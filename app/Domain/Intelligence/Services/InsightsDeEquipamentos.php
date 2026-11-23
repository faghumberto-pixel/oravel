<?php

namespace App\Domain\Intelligence\Services;

/**
 * Achados da análise por equipamento, escritos em português e COM NOMES ("avaliar venda ou reforma dos patrimônios A, B e C"):
 * alimentam o Diagnóstico, o Plano de evolução, os relatórios e o resumo do Assistente de IA. Só lê o que já foi calculado.
 */
class InsightsDeEquipamentos
{
    private const GRAV = ['critico' => 0, 'atencao' => 1, 'info' => 2];

    public function __construct(private EquipamentosService $svc = new EquipamentosService) {}

    /**
     * @return list<array{chave: string, gravidade: string, titulo: string, texto: string, patrimonios: list<string>, mais: int, classificacao: ?string, acao: ?string, prazo: ?int, prioridade: ?string}>
     */
    public function do_snapshot(string $tenantId, string $snapshotId): array
    {
        $r = $this->svc->resumo($tenantId, $snapshotId);
        $c = $r['por_classe'];
        $v = $r['valor_por_classe'];
        $out = [];

        $porClasse = function (string $chave, string $classe, string $gravidade, string $titulo, callable $texto, string $verbo, int $prazo, string $prioridade) use ($tenantId, $snapshotId, $c, &$out) {
            $n = $c[$classe] ?? 0;
            if ($n < 1) {
                return;
            }
            $linhas = $this->svc->linhas($tenantId, $snapshotId, ['classificacao' => $classe], 5)['linhas'];
            $pats = array_column($linhas, 'patrimonio');
            $mais = max(0, $n - count($pats));
            $out[] = ['chave' => $chave, 'gravidade' => $gravidade, 'titulo' => $titulo, 'texto' => $texto($n, $this->nomes($pats, $mais)), 'patrimonios' => $pats, 'mais' => $mais,
                'classificacao' => $classe, 'acao' => (str_ends_with($verbo, ' de') ? substr($verbo, 0, -3).' '.($n === 1 ? 'do patrimônio ' : 'dos patrimônios ') : $verbo.' '.($n === 1 ? 'o patrimônio ' : 'os patrimônios ')).$this->nomes($pats, $mais).'.', 'prazo' => $prazo, 'prioridade' => $prioridade];
        };

        $reais = fn (float $x) => 'R$ '.number_format($x, 0, ',', '.');
        $limite = number_format((float) config('intelligence.equipamentos.limiar_trocar_vender', 0.6) * 100, 0).'%';

        $porClasse('trocar_vender', 'trocar_vender', 'critico', 'Equipamentos para decidir: trocar ou vender',
            fn ($n, $nomes) => "{$n} ".($n === 1 ? 'equipamento já custa' : 'equipamentos já custam')." mais de {$limite} do valor em manutenção: {$nomes}. Somam ".$reais($v['trocar_vender'] ?? 0).' em valor.',
            'Avaliar a venda ou a reforma de', 30, 'Alta');
        $porClasse('risco_parada', 'risco_parada', 'atencao', 'Equipamentos em risco de parada',
            fn ($n, $nomes) => "{$n} ".($n === 1 ? 'equipamento passou' : 'equipamentos passaram').' o mês muito tempo indisponível e com corretivas: '.$nomes.'.',
            'Priorizar na oficina', 30, 'Alta');
        $porClasse('revisar', 'revisar', 'atencao', 'Equipamentos que pedem revisão técnica',
            fn ($n, $nomes) => "{$n} ".($n === 1 ? 'equipamento mostra' : 'equipamentos mostram').' custo ou reincidência acima do esperado: '.$nomes.'.',
            'Abrir revisão técnica em', 60, 'Média');
        $porClasse('subutilizado', 'subutilizado', 'atencao', 'Equipamentos parados sem contrato',
            fn ($n, $nomes) => "{$n} ".($n === 1 ? 'equipamento em bom estado está parado' : 'equipamentos em bom estado estão parados').' sem contrato: '.$nomes.'. Valor parado: '.$reais($v['subutilizado'] ?? 0).'.',
            'Ofertar ou vender', 60, 'Média');

        $re = $this->svc->reincidencias($tenantId, $snapshotId, 5);
        if ($re['total'] >= 5 && count($re['itens']) >= 2 && $re['topo_pct'] >= 40) {
            $pats = array_column($re['itens'], 'patrimonio');
            $nomes = $this->nomes($pats, 0);
            $out[] = ['chave' => 'reincidencia', 'gravidade' => 'atencao', 'titulo' => 'Reincidência concentrada em poucos equipamentos',
                'texto' => count($pats).' equipamentos respondem por '.number_format($re['topo_pct'], 0).'% das corretivas reincidentes do mês ('.$re['total'].' no total): '.$nomes.'.',
                'patrimonios' => $pats, 'mais' => 0, 'classificacao' => null, 'acao' => 'Tratar a causa-raiz das reincidências dos patrimônios '.$nomes.'.', 'prazo' => 60, 'prioridade' => 'Média'];
        }

        if ($r['total'] >= 10) {
            $topo = (int) ceil($r['total'] * 0.2);
            $p = $this->svc->pareto($tenantId, $snapshotId, $topo);
            if ($p['itens'] !== []) {
                $out[] = ['chave' => 'pareto', 'gravidade' => $p['topo_pct'] >= 60 ? 'atencao' : 'info', 'titulo' => 'Concentração do custo de manutenção',
                    'texto' => '20% dos equipamentos ('.count($p['itens']).') concentram '.number_format($p['topo_pct'], 0).'% do custo de manutenção do mês.',
                    'patrimonios' => array_slice(array_column($p['itens'], 'patrimonio'), 0, 5), 'mais' => max(0, count($p['itens']) - 5), 'classificacao' => null, 'acao' => null, 'prazo' => null, 'prioridade' => null];
            }
        }

        usort($out, fn ($a, $b) => self::GRAV[$a['gravidade']] <=> self::GRAV[$b['gravidade']]);

        return $out;
    }

    /** "A, B e C" ou "A, B, C e mais 7". */
    public function nomes(array $patrimonios, int $mais): string
    {
        if ($mais > 0) {
            return implode(', ', $patrimonios).' e mais '.$mais;
        }
        if (count($patrimonios) <= 1) {
            return (string) ($patrimonios[0] ?? '');
        }
        $ultimo = array_pop($patrimonios);

        return implode(', ', $patrimonios).' e '.$ultimo;
    }
}

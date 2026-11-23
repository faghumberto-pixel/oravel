<?php

namespace App\Domain\Intelligence\Services;

use App\Models\AssistenteRelatorio;
use Carbon\Carbon;

/**
 * Foto do Dashboard Executivo como relatório compartilhável: usa a MESMA página pública mínima do Assistente
 * (/assistente/r/{token}: sem login, link longo com validade, botão de WhatsApp e de imprimir/salvar PDF).
 * Quem abre o link vê só a foto do momento, nunca o sistema.
 */
class RelatorioDoPainel
{
    public const AREA = 'intelligence';

    /** @return array{titulo: string, resumo: string, linhas: list<array<string, string>>} */
    public function montar(array $r): array
    {
        $data = Carbon::parse($r['atual']['data'])->format('d/m/Y');
        $g = $r['atual']['geral'];

        $linhas = [];
        foreach ($r['areas'] as $a) {
            $linhas[] = ['Área' => $a['rotulo'], 'Indicador' => $a['chave'] === 'geral' ? 'Nota geral (0 a 100)' : 'Nota da área (0 a 100)',
                'Atual' => number_format($a['nota'], 0).' · '.$a['faixa'], 'Anterior' => $a['delta'] === null ? '—' : number_format($a['nota'] - $a['delta'], 0),
                'Variação' => $this->variacao($a['delta'], 'pts')];
        }
        $areas = config('intelligence.areas');
        foreach ($r['indicadores'] as $i) {
            $linhas[] = ['Área' => $areas[$i['area']] ?? $i['area'], 'Indicador' => $i['titulo'], 'Atual' => $this->num($i['valor'], $i['unidade']),
                'Anterior' => $i['anterior'] === null ? '—' : $this->num($i['anterior'], $i['unidade']),
                'Variação' => $this->variacao($i['delta'], $i['unidade'] === '%' ? 'pts' : $i['unidade'], $i['sentido'])];
        }

        $resumo = $g ? 'Índice geral da empresa: '.number_format($g['nota'], 0).' ('.$g['faixa'].')'
            .($g['delta'] === null ? '.' : ', '.($g['delta'] >= 0 ? '+' : '−').number_format(abs($g['delta']), 1, ',', '.').' pts desde '.Carbon::parse($r['anterior'])->format('d/m/Y').'.') : '';
        if ($r['destaques']) {
            $resumo .= "\n\nO que mudou:\n• ".implode("\n• ", $r['destaques']);
        }

        return ['titulo' => "Intelligence — saúde da empresa em {$data}", 'resumo' => trim($resumo), 'linhas' => $linhas];
    }

    /** @return array{titulo: string, resumo: string, linhas: list<array<string, string>>} */
    public function montarDiagnostico(array $r): array
    {
        $d = app(PlanoDeEvolucao::class)->diagnostico($r);
        $data = Carbon::parse($r['atual']['data'])->format('d/m/Y');
        $linhas = [];
        foreach ($d['atencao'] as $i) {
            $linhas[] = ['Situação' => $i['faixa'].' (nota '.number_format($i['nota'], 0).')', 'Indicador' => $i['titulo'], 'Área' => $i['area_rotulo'],
                'Hoje' => $this->num($i['valor'], $i['unidade']), 'Meta' => $this->num($i['meta'], $i['unidade']), 'Por que importa' => $i['impacto']];
        }
        foreach ($d['fortes'] as $i) {
            $linhas[] = ['Situação' => 'Ponto forte (nota '.number_format($i['nota'], 0).')', 'Indicador' => $i['titulo'], 'Área' => $i['area_rotulo'],
                'Hoje' => $this->num($i['valor'], $i['unidade']), 'Meta' => $this->num($i['meta'], $i['unidade']), 'Por que importa' => $i['impacto']];
        }
        $resumo = $d['atencao'] ? count($d['atencao']).' ponto(s) de atenção; o mais grave é '.mb_strtolower($d['atencao'][0]['titulo']).'.' : 'Nenhum ponto crítico nesta fotografia.';

        return ['titulo' => "Intelligence — diagnóstico em {$data}", 'resumo' => $resumo, 'linhas' => $linhas];
    }

    /** @return array{titulo: string, resumo: string, linhas: list<array<string, string>>} */
    public function montarPlano(array $r): array
    {
        $p = app(PlanoDeEvolucao::class)->plano($r);
        $data = Carbon::parse($r['atual']['data'])->format('d/m/Y');
        $linhas = [];
        foreach ($p['itens'] as $i) {
            $linhas[] = ['Prazo' => 'até '.$i['prazo'].' dias', 'Prioridade' => $i['prioridade'], 'Indicador' => $i['titulo'], 'Hoje' => $this->num($i['valor'], $i['unidade']),
                'Meta do ciclo' => $this->num($i['alvo_ciclo'], $i['unidade']), 'Meta ideal' => $this->num($i['meta'], $i['unidade']), 'O que fazer' => implode(' • ', $i['acoes'])];
        }
        $resumo = $p['itens'] ? 'Foco do ciclo: '.$p['foco'].'. '.count($p['itens']).' ação(ões) em 30, 60 e 90 dias. Reimporte a planilha em 30 dias para medir o avanço.' : 'Todos os indicadores com nota estão em 80 ou mais: sem ação urgente.';

        return ['titulo' => "Intelligence — plano de evolução em {$data}", 'resumo' => $resumo, 'linhas' => $linhas];
    }

    /**
     * Lista de decisão da frota: tudo que NÃO está saudável, dos mais urgentes aos menos. Null se a empresa não tem equipamentos analisados.
     *
     * @return ?array{titulo: string, resumo: string, linhas: list<array<string, string>>}
     */
    public function montarEquipamentos(string $tenantId): ?array
    {
        $svc = app(EquipamentosService::class);
        $snap = $svc->snapshot($tenantId);
        if (! $snap) {
            return null;
        }
        $resumo = $svc->resumo($tenantId, $snap['id']);
        $rot = AnaliseDeEquipamentos::ROTULOS;
        $linhas = [];
        foreach ($svc->todas($tenantId, $snap['id']) as $e) {
            if (in_array($e['classificacao'], ['saudavel', 'sem_dados'], true)) {
                continue;
            }
            $linhas[] = ['Equipamento' => $e['patrimonio'].' · '.$e['nome'], 'Situação' => $rot[$e['classificacao']] ?? $e['classificacao'], 'Motivo' => $e['motivo'],
                'Custo ÷ valor' => $e['razao_custo_valor'] === null ? '—' : $this->num($e['razao_custo_valor'] * 100, '%'),
                'Utilização' => $e['utilizacao_pct'] === null ? '—' : $this->num($e['utilizacao_pct'], '%'),
                'Tendência' => ['piorando' => '▼ piorando', 'melhorando' => '▲ melhorando', 'estavel' => '■ estável'][$e['tendencia'] ?? ''] ?? '—'];
        }
        $c = $resumo['por_classe'];
        $texto = $resumo['total'].' equipamentos analisados: '.($c['trocar_vender'] ?? 0).' para considerar trocar ou vender'
            .($resumo['valor_trocar_vender'] > 0 ? ' (R$ '.number_format($resumo['valor_trocar_vender'], 0, ',', '.').' em valor)' : '').', '.($c['revisar'] ?? 0).' para revisar, '
            .($c['subutilizado'] ?? 0).' subutilizados e '.($c['risco_parada'] ?? 0).' em risco de parada.';

        return ['titulo' => 'Intelligence — equipamentos em '.Carbon::parse($snap['data'])->format('d/m/Y'), 'resumo' => $texto, 'linhas' => $linhas];
    }

    public function criarEquipamentos(string $tenantId, ?string $userId): ?AssistenteRelatorio
    {
        $m = $this->montarEquipamentos($tenantId);
        if ($m === null) {
            return null;
        }
        $existente = AssistenteRelatorio::query()->where('area', self::AREA)->where('titulo', $m['titulo'])->where('user_id', $userId)
            ->where('resumo', $m['resumo'])->where('created_at', '>', now()->subHour())->latest('created_at')->first();

        return $existente ?? AssistenteRelatorio::criar(self::AREA, $m['titulo'], $m['resumo'], $m['linhas'], $userId);
    }

    /** Reaproveita o relatório do mesmo usuário e da mesma fotografia gerado na última hora (clicar duas vezes não gera dois). */
    public function criar(array $r, ?string $userId, string $tipo = 'painel'): AssistenteRelatorio
    {
        $m = match ($tipo) {
            'diagnostico' => $this->montarDiagnostico($r),
            'plano' => $this->montarPlano($r),
            default => $this->montar($r),
        };

        $existente = AssistenteRelatorio::query()->where('area', self::AREA)->where('titulo', $m['titulo'])
            ->where('user_id', $userId)->where('resumo', $m['resumo'])->where('created_at', '>', now()->subHour())->latest('created_at')->first();

        return $existente ?? AssistenteRelatorio::criar(self::AREA, $m['titulo'], $m['resumo'], $m['linhas'], $userId);
    }

    private function num(float $v, string $unidade): string
    {
        return number_format($v, abs($v) >= 100 ? 0 : 1, ',', '.').($unidade !== '' ? ' '.$unidade : '');
    }

    private function variacao(?float $delta, string $unidade, ?string $sentido = null): string
    {
        if ($delta === null) {
            return '—';
        }
        $sinal = $delta > 0 ? '▲ ' : ($delta < 0 ? '▼ ' : '');
        $texto = $sinal.$this->num(abs($delta), $unidade);

        return match ($sentido) {
            'melhor' => $texto.' (melhorou)',
            'pior' => $texto.' (piorou)',
            default => $texto,
        };
    }
}

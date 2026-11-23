<?php

namespace App\Domain\Intelligence\Services;

use Carbon\Carbon;

/**
 * Aba "Equipamentos" → análise de cada equipamento. Puro (só recebe e devolve arrays): indicadores por equipamento, classificação
 * com o MOTIVO escrito, tendência contra o mês anterior e os campos da empresa que a aba passa a gerar.
 *
 * Classificação (a primeira regra que bate), limiares em config('intelligence.equipamentos'): trocar_vender, revisar,
 * subutilizado, risco_parada, saudavel; sem_dados quando nada pôde ser avaliado. Limiares de REFERÊNCIA, a validar com especialista.
 */
class AnaliseDeEquipamentos
{
    public const GRAVIDADE = ['trocar_vender' => 4, 'revisar' => 3, 'risco_parada' => 3, 'subutilizado' => 2, 'saudavel' => 0, 'sem_dados' => 0];

    public const ROTULOS = [
        'trocar_vender' => 'Considerar trocar ou vender', 'revisar' => 'Revisar', 'subutilizado' => 'Subutilizado',
        'risco_parada' => 'Em risco de parada', 'saudavel' => 'Saudável', 'sem_dados' => 'Sem dados suficientes',
    ];

    /**
     * @param  list<array<string, mixed>>  $linhas  uma por equipamento (chaves de Catalogo::equipamentos(); ausente/null = não informado)
     * @param  array<string, array{linha: array<string, mixed>, analise: array<string, mixed>}>  $anteriores  patrimônio => linha e análise do mês anterior
     * @return array<string, array<string, mixed>> patrimônio => análise
     */
    public function analisar(array $linhas, string $dataReferencia, array $anteriores = []): array
    {
        $cfg = (array) config('intelligence.equipamentos');
        $ref = Carbon::parse($dataReferencia);
        $diasMes = $ref->daysInMonth;
        $out = [];

        foreach ($linhas as $l) {
            $pat = (string) $l['patrimonio'];
            $ant = $anteriores[$pat] ?? null;
            $valor = $this->n($l, 'valor');
            $receita = $this->n($l, 'receita');
            $custoMes = $this->n($l, 'custo_mes');
            $acum = $this->n($l, 'custo_acumulado');
            $corretivas = $this->n($l, 'os_corretivas');
            $reinc = $this->n($l, 'reincidencias');
            $locado = $this->n($l, 'dias_locado');
            $indisp = $this->n($l, 'dias_indisponivel');
            $ano = $this->n($l, 'ano_fabricacao');

            $m = [
                'utilizacao_pct' => $locado === null ? null : round(min(100, $locado / $diasMes * 100), 2),
                'disponibilidade_pct' => $indisp === null ? null : round(max(0, 100 - $indisp / $diasMes * 100), 2),
                'retorno_anual_pct' => ($valor > 0 && $receita !== null && $custoMes !== null) ? round(($receita - $custoMes) * (365 / $diasMes) / $valor * 100, 2) : null,
                'razao_custo_valor' => ($valor > 0 && $acum !== null) ? round($acum / $valor, 4) : null,
                'custo_sobre_receita_pct' => ($receita > 0 && $custoMes !== null) ? round($custoMes / $receita * 100, 2) : null,
                'reincidencia_pct' => ($corretivas > 0 && $reinc !== null) ? round($reinc / $corretivas * 100, 2) : null,
                'idade_anos' => $ano === null ? null : max(0, $ref->year - (int) $ano),
            ];

            [$classe, $motivo] = $this->classificar($m, $l, $ant, $cfg);
            $gravAnt = $ant ? (self::GRAVIDADE[$ant['analise']['classificacao'] ?? 'sem_dados'] ?? 0) : null;
            $grav = self::GRAVIDADE[$classe];
            $tendencia = $gravAnt === null || $classe === 'sem_dados' ? null : ($grav > $gravAnt ? 'piorando' : ($grav < $gravAnt ? 'melhorando' : 'estavel'));

            $out[$pat] = $m + ['classificacao' => $classe, 'motivo' => $motivo, 'tendencia' => $tendencia, 'versao_regras' => (int) ($cfg['versao_regras'] ?? 1)];
        }

        return $out;
    }

    /** @return array{0: string, 1: string} [classificação, motivo] */
    private function classificar(array $m, array $l, ?array $ant, array $cfg): array
    {
        $trocar = (float) $cfg['limiar_trocar_vender'];
        $revisar = (float) $cfg['limiar_revisar'];
        $baixa = (float) $cfg['utilizacao_baixa_pct'];
        $razao = $m['razao_custo_valor'];
        $retorno = $m['retorno_anual_pct'];
        $retornoAnt = $ant['analise']['retorno_anual_pct'] ?? null;

        // 1) trocar ou vender
        if ($razao !== null && $razao >= $trocar) {
            return ['trocar_vender', 'O custo de manutenção acumulado já é de '.$this->pct($razao * 100).' do valor do equipamento (o limite é de '.$this->pct($trocar * 100).').'];
        }
        if ($retorno !== null && $retorno < 0 && $retornoAnt !== null && (float) $retornoAnt < 0 && $m['utilizacao_pct'] !== null && $m['utilizacao_pct'] < $baixa) {
            return ['trocar_vender', 'O retorno foi negativo por dois meses seguidos ('.$this->pct($retorno).' ao ano) e a utilização está em '.$this->pct($m['utilizacao_pct']).', abaixo de '.$this->pct($baixa).'.'];
        }

        // 2) revisar (junta todos os motivos que bateram)
        $motivos = [];
        if ($razao !== null && $razao >= $revisar) {
            $motivos[] = 'O custo de manutenção acumulado é de '.$this->pct($razao * 100).' do valor do equipamento (revisar a partir de '.$this->pct($revisar * 100).').';
        }
        $minCorr = (int) $cfg['reincidencia_minimo_corretivas'];
        if ($m['reincidencia_pct'] !== null && $m['reincidencia_pct'] >= (float) $cfg['reincidencia_revisar_pct'] && ($this->n($l, 'os_corretivas') ?? 0) >= $minCorr) {
            $motivos[] = (int) $l['reincidencias'].' de '.(int) $l['os_corretivas'].' corretivas do mês foram reincidência ('.$this->pct($m['reincidencia_pct']).').';
        }
        if ($m['custo_sobre_receita_pct'] !== null && $m['custo_sobre_receita_pct'] >= (float) $cfg['custo_receita_revisar_pct']) {
            $motivos[] = 'A manutenção do mês consumiu '.$this->pct($m['custo_sobre_receita_pct']).' da receita deste equipamento.';
        }
        if ($motivos !== []) {
            return ['revisar', implode(' ', $motivos)];
        }

        // 3) subutilizado
        if ($m['utilizacao_pct'] !== null && $m['disponibilidade_pct'] !== null && $m['utilizacao_pct'] < $baixa && $m['disponibilidade_pct'] >= (float) $cfg['disponibilidade_minima_subutilizado_pct']) {
            return ['subutilizado', 'Utilização de '.$this->pct($m['utilizacao_pct']).' com disponibilidade de '.$this->pct($m['disponibilidade_pct']).': equipamento em boas condições e parado sem contrato. Vale ofertar ou vender.'];
        }

        // 4) risco de parada
        $corr = $this->n($l, 'os_corretivas');
        if ($m['disponibilidade_pct'] !== null && $m['disponibilidade_pct'] < (float) $cfg['disponibilidade_risco_pct'] && $corr !== null && $corr >= 1) {
            $corrAnt = $ant ? $this->n($ant['linha'], 'os_corretivas') : null;
            if ($corrAnt === null || $corr > $corrAnt) {
                return ['risco_parada', 'Indisponível em '.$this->pct(100 - $m['disponibilidade_pct']).' do mês, com '.(int) $corr.' corretiva(s)'.($corrAnt === null ? '.' : ' (mês anterior: '.(int) $corrAnt.').')];
            }
        }

        // 5) saudável, ou sem dados para avaliar nada
        $avaliou = collect($m)->except('idade_anos')->contains(fn ($v) => $v !== null);
        if (! $avaliou) {
            return ['sem_dados', 'Dados insuficientes para avaliar: preencha valor, receita, custo ou dias locado e indisponível.'];
        }

        return ['saudavel', 'Nenhum sinal de alerta nos dados informados.'];
    }

    /**
     * Campos da aba "Dados" que a aba "Equipamentos" passa a gerar (só os que ela tem como calcular).
     *
     * @return array<string, int|float>
     */
    public function agregados(array $linhas, string $dataReferencia): array
    {
        $diasMes = Carbon::parse($dataReferencia)->daysInMonth;
        $soma = function (string $k) use ($linhas): ?float {
            $v = array_filter(array_column($linhas, $k), fn ($x) => $x !== null);

            return $v === [] ? null : (float) array_sum($v);
        };
        $out = ['frota_total' => count($linhas)];

        $locado = $soma('dias_locado');
        $indisp = $soma('dias_indisponivel');
        if ($locado !== null) {
            $out['frota_locada'] = (int) round($locado / $diasMes);
        }
        if ($indisp !== null) {
            $out['frota_indisponivel'] = (int) round($indisp / $diasMes);
        }
        // arredondar os dois para cima pode passar do total por 1 unidade: o excedente sai da indisponível
        if (isset($out['frota_locada'], $out['frota_indisponivel']) && $out['frota_total'] < $out['frota_locada'] + $out['frota_indisponivel']) {
            $out['frota_indisponivel'] = max(0, $out['frota_total'] - $out['frota_locada']);
        }
        foreach (['valor' => 'valor_frota', 'receita' => 'receita_mensal', 'custo_mes' => 'custo_manutencao'] as $de => $para) {
            if (($v = $soma($de)) !== null) {
                $out[$para] = round($v, 2);
            }
        }
        foreach (['os_corretivas', 'os_preventivas'] as $k) {
            if (($v = $soma($k)) !== null) {
                $out[$k] = (int) $v;
            }
        }

        return $out;
    }

    /**
     * Números da empresa que só existem com a classificação por equipamento (alimentam os indicadores novos).
     *
     * @param  array<string, array<string, mixed>>  $analises  patrimônio => análise
     * @return array<string, float>
     */
    public function resumoDaEmpresa(array $linhas, array $analises): array
    {
        $out = [];
        $corretivas = 0;
        $reinc = 0;
        $temReinc = false;
        foreach ($linhas as $l) {
            if (($l['os_corretivas'] ?? null) !== null && ($l['reincidencias'] ?? null) !== null) {
                $corretivas += (int) $l['os_corretivas'];
                $reinc += (int) $l['reincidencias'];
                $temReinc = true;
            }
        }
        if ($temReinc && $corretivas > 0) {
            $out['equip_reincidencia_pct'] = round($reinc / $corretivas * 100, 2);
        }

        $avaliados = array_filter($analises, fn ($a) => $a['classificacao'] !== 'sem_dados');
        if ($avaliados !== []) {
            $sub = count(array_filter($avaliados, fn ($a) => $a['classificacao'] === 'subutilizado'));
            $out['equip_subutilizados_pct'] = round($sub / count($avaliados) * 100, 2);
        }

        $valorTotal = 0.0;
        $valorTrocar = 0.0;
        foreach ($linhas as $l) {
            $v = (float) ($l['valor'] ?? 0);
            $valorTotal += $v;
            if (($analises[$l['patrimonio']]['classificacao'] ?? null) === 'trocar_vender') {
                $valorTrocar += $v;
            }
        }
        if ($valorTotal > 0) {
            $out['equip_valor_trocar_vender_pct'] = round($valorTrocar / $valorTotal * 100, 2);
        }

        return $out;
    }

    private function n(array $l, string $k): ?float
    {
        return isset($l[$k]) && $l[$k] !== '' ? (float) $l[$k] : null;
    }

    private function pct(float $v): string
    {
        return number_format($v, abs($v) >= 100 ? 0 : 1, ',', '.').'%';
    }
}

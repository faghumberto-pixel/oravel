<?php

namespace App\Support\IntelligenceSite;

/**
 * A locadora fictícia da página oravel.com.br/intelligence. Lê config('intelligence_site.exemplo') e CALCULA tudo o que deriva
 * dele (variações, equipamentos parados, custos). Nenhum número da página pode ser digitado fora daqui ou do config.
 */
class Exemplo
{
    public function __construct(private array $d) {}

    public static function doConfig(): self
    {
        return new self((array) config('intelligence_site.exemplo'));
    }

    public function frota(): int
    {
        return (int) $this->d['frota'];
    }

    // ---- índice de saúde ----
    public function indice(string $mes): int
    {
        return (int) $this->d['indice'][$mes];
    }

    public function indiceVariacao(): int
    {
        return $this->indice('out') - $this->indice('ago');
    }

    // ---- disponibilidade ----
    public function disponibilidade(string $mes): int
    {
        return (int) $this->d['disponibilidade'][$mes];
    }

    public function disponibilidadePontos(): int
    {
        return $this->disponibilidade('out') - $this->disponibilidade('ago');
    }

    /** Equipamentos que não estão prontos para locar no último mês. */
    public function parados(): int
    {
        return $this->frota() - (int) round($this->frota() * $this->disponibilidade('out') / 100);
    }

    // ---- atraso ----
    public function atraso(string $mes): float
    {
        return (float) $this->d['atraso_pct'][$mes];
    }

    public function atrasoMeta(): float
    {
        return $this->atraso('meta');
    }

    public function atrasoPontos(): float
    {
        return round($this->atraso('out') - $this->atraso('ago'), 1);
    }

    // ---- oficina ----
    public function oficina(string $mes): int
    {
        return (int) $this->d['oficina'][$mes];
    }

    public function oficinaVariacao(): int
    {
        return $this->oficina('out') - $this->oficina('ago');
    }

    // ---- equipamento parado (calculadora) ----
    public function diaria(): int
    {
        return (int) $this->d['diaria_media'];
    }

    public function diasParados(): int
    {
        return (int) $this->d['dias_parados'];
    }

    public function custoParadoMes(): int
    {
        return $this->parados() * $this->diaria() * $this->diasParados();
    }

    public function custoParadoAno(): int
    {
        return $this->custoParadoMes() * 12;
    }

    // ---- manutenção ----
    public function acima60(): int
    {
        return (int) $this->d['equipamentos_acima_60'];
    }

    /** @return list<array{nome: string, patrimonio: string, valor: int, manutencao: int, pct: int, situacao: string}> */
    public function equipamentos(): array
    {
        return array_map(function (array $e) {
            $pct = (int) round($e['manutencao'] / $e['valor'] * 100);

            return $e + ['pct' => $pct, 'situacao' => $pct >= 60 ? 'Trocar ou vender' : ($pct >= 40 ? 'Revisar' : 'Saudável')];
        }, $this->d['equipamentos_exemplo']);
    }

    public function destaque(): array
    {
        return $this->equipamentos()[0];
    }

    public function pergunta(): string
    {
        return (string) $this->d['pergunta_assistente'];
    }

    // ---- formatação pt-BR ----
    public static function brl(int|float $v): string
    {
        return 'R$ '.number_format($v, 0, ',', '.');
    }

    public static function pct(int|float $v, int $casas = 0): string
    {
        return number_format($v, $casas, ',', '.').'%';
    }

    /** +16, -2,7: sinal explícito, vírgula decimal e sinal de menos de verdade. */
    public static function delta(int|float $v, int $casas = 0): string
    {
        return ($v > 0 ? '+' : ($v < 0 ? '−' : '')).number_format(abs($v), $casas, ',', '.');
    }
}

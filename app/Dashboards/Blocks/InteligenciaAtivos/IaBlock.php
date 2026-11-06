<?php

namespace App\Dashboards\Blocks\InteligenciaAtivos;

use App\Dashboards\Block;
use App\Models\Asset;
use App\Models\User;
use App\Services\CustoManutencaoService;
use App\Support\Tenancy;
use Illuminate\Support\Collection;

/**
 * Base dos blocos de "Inteligência de Ativos" (custo de manutenção e localização dos equipamentos). Só aparecem
 * para quem tem a chave no contrato (menu_custo_equipamentos / menu_localizacao_equipamentos).
 */
abstract class IaBlock extends Block
{
    public const STATUS_CORES = [
        'locado' => '#2563eb', 'disponivel' => '#16a34a', 'manutencao' => '#dc2626', 'operando' => '#0891b2',
        'reservado' => '#db2777', 'quarentena' => '#9333ea', 'aguardando_triagem' => '#f97316',
    ];

    public static function module(): string
    {
        return 'Inteligência de Ativos';
    }

    /** Modulo de tabela exigido (como nos outros blocos); a chave de contrato da Inteligencia de Ativos e' checada em visibleTo(). */
    public static function requiredFeature(): string
    {
        return 'tabela_assets';
    }

    /** Chave de contrato (menu_*) que libera o bloco: custo por padrao; os blocos de mapa usam a de localizacao. */
    public static function chaveDoContrato(): string
    {
        return CustoManutencaoService::FEATURE;
    }

    public static function visibleTo(?User $user): bool
    {
        if (! parent::visibleTo($user)) {
            return false;
        }

        return Tenancy::current()?->hasFeature(static::chaveDoContrato()) ?? false;
    }

    public static function sourceModel(): string
    {
        return Asset::class;
    }

    /** Ranking de custo dos ultimos 12 meses (uma consulta; vale para o bloco). @return Collection<int, array<string, mixed>> */
    protected function ranking(): Collection
    {
        return app(CustoManutencaoService::class)->ranking(12);
    }

    protected function brl(float $v): string
    {
        return 'R$ '.number_format($v, 2, ',', '.');
    }

    /** R$ 8,2 mil / R$ 211 mil / R$ 1,2 mi: legivel em cartao pequeno. */
    protected function compacto(float $v): string
    {
        $abs = abs($v);
        $sinal = $v < 0 ? '-' : '';

        return match (true) {
            $abs >= 1_000_000 => $sinal.'R$ '.number_format($abs / 1_000_000, 1, ',', '.').' mi',
            $abs >= 100_000 => $sinal.'R$ '.number_format($abs / 1000, 0, ',', '.').' mil',
            $abs >= 1000 => $sinal.'R$ '.number_format($abs / 1000, 1, ',', '.').' mil',
            default => $sinal.'R$ '.number_format($abs, 0, ',', '.'),
        };
    }

    protected function tom(string $corDaSituacao): string
    {
        return ['red' => 'crit', 'amber' => 'warn', 'emerald' => 'ok'][$corDaSituacao] ?? 'n';
    }

    protected function nomeCurto(array $linha): string
    {
        return trim(($linha['patrimonio'] ? $linha['patrimonio'].' · ' : '').$linha['nome']);
    }
}

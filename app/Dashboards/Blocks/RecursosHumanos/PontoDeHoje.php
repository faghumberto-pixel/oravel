<?php

namespace App\Dashboards\Blocks\RecursosHumanos;

use App\Dashboards\Block;
use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\Employee;
use App\Models\TimeClock;

class PontoDeHoje extends Block
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'rh.ponto_hoje';
    }

    public static function title(): string
    {
        return 'Ponto de hoje';
    }

    public static function subtitle(): string
    {
        return 'Quem já bateu o ponto de entrada';
    }

    public static function size(): int
    {
        return 3;
    }

    public static function module(): string
    {
        return 'Equipe';
    }

    public static function requiredFeature(): string
    {
        return 'tabela_time_clocks';
    }

    public static function sourceModel(): string
    {
        return TimeClock::class;
    }

    public function data(array $config): array
    {
        $bateram = TimeClock::query()->where('tipo', TimeClock::TIPO_ENTRADA)->whereDate('recorded_at', today())->distinct()->count('employee_id');
        $ativos = Employee::query()->where('status', Employee::STATUS_ATIVO)->count();
        $faltam = max(0, $ativos - $bateram);

        return Payload::kpi($bateram, 'de '.$ativos, $faltam > 0 ? $faltam.' ainda sem ponto' : 'todos presentes', $faltam > 0 ? 'n' : 'up', $ativos > 0 ? ['pct' => (int) round($bateram / $ativos * 100)] : null);
    }
}

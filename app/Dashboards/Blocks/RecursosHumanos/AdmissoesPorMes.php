<?php

namespace App\Dashboards\Blocks\RecursosHumanos;

use App\Dashboards\Block;
use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\Employee;

class AdmissoesPorMes extends Block
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'rh.admissoes_por_mes';
    }

    public static function title(): string
    {
        return 'Admissões por mês';
    }

    public static function subtitle(): string
    {
        return 'Últimos 12 meses';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function module(): string
    {
        return 'Equipe';
    }

    public static function requiredFeature(): string
    {
        return 'tabela_employees';
    }

    public static function sourceModel(): string
    {
        return Employee::class;
    }

    public function data(array $config): array
    {
        $meses = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $serie = $this->serieMensal(Employee::query()->whereNotNull('admission_date'), 'admission_date', 12);

        return Payload::line($meses->map(fn ($m) => $m->translatedFormat('M'))->all(), $serie);
    }
}

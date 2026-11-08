<?php

namespace App\Dashboards\Blocks\RecursosHumanos;

use App\Dashboards\Block;
use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\Employee;

class ColaboradoresAtivos extends Block
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'rh.colaboradores_ativos';
    }

    public static function title(): string
    {
        return 'Colaboradores ativos';
    }

    public static function subtitle(): string
    {
        return 'Quem está trabalhando hoje na empresa';
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
        return 'tabela_employees';
    }

    public static function sourceModel(): string
    {
        return Employee::class;
    }

    public function data(array $config): array
    {
        $ativos = Employee::query()->where('status', Employee::STATUS_ATIVO)->count();
        $afastados = Employee::query()->where('status', Employee::STATUS_AFASTADO)->count();

        return Payload::kpi($ativos, $ativos === 1 ? 'colaborador' : 'colaboradores', $afastados > 0 ? $afastados.' afastado(s)' : 'nenhum afastado', $afastados > 0 ? 'n' : 'up', null, $this->serieMensal(Employee::query()->whereNotNull('admission_date'), 'admission_date', 6));
    }
}

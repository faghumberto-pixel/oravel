<?php

namespace App\Dashboards\Blocks\RecursosHumanos;

use App\Dashboards\Block;
use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\Employee;
use App\Models\EmployeeCertification;

class CertificacoesAVencer extends Block
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'rh.certificacoes_a_vencer';
    }

    public static function title(): string
    {
        return 'Certificações e treinamentos a vencer';
    }

    public static function subtitle(): string
    {
        return 'Vencidos e nos próximos 60 dias';
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
        $limite = now()->addDays(60)->toDateString();
        $linhas = EmployeeCertification::query()->with('employee:id,name')->whereNotNull('data_validade')->whereDate('data_validade', '<=', $limite)
            ->orderBy('data_validade')->limit(8)->get()->map(function (EmployeeCertification $c) {
                $dias = (int) now()->startOfDay()->diffInDays($c->data_validade->startOfDay(), false);

                return [
                    $c->employee?->name ?? '—', $c->norma, $c->data_validade->format('d/m/Y'),
                    $dias < 0 ? Payload::pill('crit', 'vencida há '.abs($dias).' d') : Payload::pill($dias <= 15 ? 'warn' : 'ok', 'em '.$dias.' d'),
                ];
            })->all();

        return Payload::table(['Colaborador', 'Norma / curso', '>Validade', '>Situação'], $linhas, 'Nenhuma certificação vencendo.');
    }
}

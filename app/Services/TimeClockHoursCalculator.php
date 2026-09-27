<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TimeClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula horas trabalhadas por dia a partir das batidas de ponto.
 * Regra: soma o tempo entre cada par entrada/saida do dia, subtraindo o
 * tempo de qualquer pausa/almoco batido entre eles (TimeClock::TIPOS_PAUSA).
 * Batida sem par (ex: entrada sem saida -- dia em andamento) nao entra na
 * soma, so aparece na lista de eventos crus pra conferencia visual.
 */
class TimeClockHoursCalculator
{
    /**
     * @return array{worked_minutes: int, expected_minutes: int, overtime_minutes: int, events: Collection, complete: bool}
     */
    public function forDate(Employee $employee, Carbon $date): array
    {
        $events = TimeClock::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('recorded_at', [$date->clone()->startOfDay(), $date->clone()->endOfDay()])
            ->orderBy('recorded_at')
            ->get();

        return $this->calculate($events, $employee);
    }

    /**
     * @return array<string, array{worked_minutes: int, expected_minutes: int, overtime_minutes: int, events: Collection, complete: bool}>
     */
    public function forRange(Employee $employee, Carbon $from, Carbon $to): array
    {
        $events = TimeClock::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('recorded_at', [$from->clone()->startOfDay(), $to->clone()->endOfDay()])
            ->orderBy('recorded_at')
            ->get()
            ->groupBy(fn (TimeClock $event) => $event->recorded_at->toDateString());

        $result = [];
        $cursor = $from->clone()->startOfDay();

        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $result[$key] = $this->calculate($events->get($key, collect()), $employee);
            $cursor->addDay();
        }

        return $result;
    }

    private function calculate(Collection $events, Employee $employee): array
    {
        $workedSeconds = 0;
        $pauseSeconds = 0;
        $entradaAt = null;
        $pausaAt = null;
        $complete = false;

        foreach ($events as $event) {
            /** @var TimeClock $event */
            if ($event->tipo === TimeClock::TIPO_ENTRADA) {
                $entradaAt = $event->recorded_at;
                $complete = false;
            } elseif (in_array($event->tipo, [TimeClock::TIPO_INICIO_PAUSA, TimeClock::TIPO_INICIO_ALMOCO, TimeClock::TIPO_INICIO_INTERVALO], true)) {
                $pausaAt = $event->recorded_at;
            } elseif (in_array($event->tipo, [TimeClock::TIPO_FIM_PAUSA, TimeClock::TIPO_FIM_ALMOCO, TimeClock::TIPO_FIM_INTERVALO], true)) {
                if ($pausaAt) {
                    $pauseSeconds += $pausaAt->diffInSeconds($event->recorded_at);
                    $pausaAt = null;
                }
            } elseif ($event->tipo === TimeClock::TIPO_SAIDA && $entradaAt) {
                $workedSeconds += $entradaAt->diffInSeconds($event->recorded_at);
                $entradaAt = null;
                $complete = true;
            }
        }

        $workedSeconds = max(0, $workedSeconds - $pauseSeconds);
        $workedMinutes = intdiv($workedSeconds, 60);
        $expectedMinutes = (int) round(((float) $employee->daily_work_hours) * 60);

        return [
            'worked_minutes' => $workedMinutes,
            'expected_minutes' => $expectedMinutes,
            'overtime_minutes' => max(0, $workedMinutes - $expectedMinutes),
            'events' => $events,
            'complete' => $complete,
            'em_andamento' => (bool) $entradaAt,
        ];
    }

    public function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%dh%02dm', $hours, $mins);
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Models\TimeClock;
use App\Services\TimeClockHoursCalculator;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

/**
 * Autoatendimento do colaborador: horas trabalhadas dos ultimos 14 dias,
 * calculadas a partir das batidas de TimeClock (ver
 * App\Services\TimeClockHoursCalculator). So' leitura, sem acao -- pra
 * corrigir uma batida errada o colaborador precisa falar com o RH (ainda
 * sem tela de edicao/admin pro TimeClock).
 */
#[Layout('layouts.checklist-mobile')]
class MinhasHoras extends Page
{
    protected static string $view = 'filament.pages.minhas-horas';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', TimeClock::class);
    }

    public function getDaysProperty(): array
    {
        $employee = Employee::where('user_id', Auth::id())->first();

        if (! $employee) {
            return [];
        }

        $to = now();
        $from = $to->clone()->subDays(13);

        $byDate = app(TimeClockHoursCalculator::class)->forRange($employee, $from, $to);

        return collect($byDate)
            ->map(fn (array $data, string $date) => [
                'date' => Carbon::parse($date),
                'worked_minutes' => $data['worked_minutes'],
                'overtime_minutes' => $data['overtime_minutes'],
                'em_andamento' => $data['em_andamento'],
                'events' => $data['events'],
            ])
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    public function formatMinutes(int $minutes): string
    {
        return app(TimeClockHoursCalculator::class)->formatMinutes($minutes);
    }
}

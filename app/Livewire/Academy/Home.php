<?php

namespace App\Livewire\Academy;

use App\Services\AcademyOverview;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Inicio da Academia: indicadores do aluno, "continue de onde parou", cursos filtraveis por
 * status e estatisticas por curso.
 */
#[Layout('academy.layout')]
class Home extends Component
{
    #[Url(as: 'filtro')]
    public string $filter = 'todos';

    public function render()
    {
        $user = auth()->user();
        $service = app(AcademyOverview::class);
        $all = $service->courses($user);

        $courses = match ($this->filter) {
            'andamento' => $all->where('status', AcademyOverview::STATUS_PROGRESS),
            'fazer' => $all->where('status', AcademyOverview::STATUS_TODO),
            'concluidos' => $all->where('status', AcademyOverview::STATUS_DONE),
            default => $all,
        };

        return view('livewire.academy.home', [
            'all' => $all,
            'courses' => $courses->values(),
            'totals' => $service->totals($user, $all),
            'resume' => $service->resume($all),
            'certificates' => $all->filter(fn ($c) => $c['certificate'])->values(),
            'readOnly' => ! $user->tenant_id,
        ]);
    }
}

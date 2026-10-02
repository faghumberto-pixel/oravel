<?php

namespace App\Livewire\Academy;

use App\Services\AcademyOverview;
use App\Services\AcademyPoints;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Barra lateral da Academia: cursos "em andamento", "a fazer" e "concluidos" com progresso.
 * Componente proprio (e nao parte do layout) porque o layout nao re-renderiza quando o aluno
 * conclui uma aula -- as paginas disparam 'academy-updated' e a barra se atualiza.
 */
class Sidebar extends Component
{
    /** Secao ativa: 'home', 'ranking', 'team' ou o slug do curso aberto. */
    public string $active = 'home';

    public function mount(): void
    {
        $path = trim((string) request()->path(), '/');
        $this->active = match (true) {
            str_contains($path, 'academia/curso/') => (string) request()->route('slug'),
            str_ends_with($path, 'academia/ranking') => 'ranking',
            str_ends_with($path, 'academia/equipe') => 'team',
            default => 'home',
        };
    }

    #[On('academy-updated')]
    public function refresh(): void
    {
        // so' re-renderiza
    }

    public function render()
    {
        $user = auth()->user();
        $service = app(AcademyOverview::class);
        $courses = $service->courses($user);

        return view('livewire.academy.sidebar', [
            'groups' => [
                'Em andamento' => $courses->where('status', AcademyOverview::STATUS_PROGRESS),
                'A fazer' => $courses->where('status', AcademyOverview::STATUS_TODO),
                'Concluídos' => $courses->where('status', AcademyOverview::STATUS_DONE),
            ],
            'points' => app(AcademyPoints::class)->total($user),
            'isAdmin' => (bool) ($user->isAdmin() && $user->tenant_id),
        ]);
    }
}

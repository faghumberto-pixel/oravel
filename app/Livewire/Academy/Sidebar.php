<?php

namespace App\Livewire\Academy;

use App\Services\AcademyOverview;
use App\Services\AcademyPoints;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Barra lateral da Academia: todos os cursos com todos os seus modulos (aulas), agrupados em
 * "em andamento", "a fazer" e "concluidos". Cada aula abre direto -- nao ha ordem obrigatoria.
 * Componente proprio (e nao parte do layout) porque o layout nao re-renderiza quando o aluno
 * conclui uma aula: as paginas disparam 'academy-updated' e a barra se atualiza.
 */
class Sidebar extends Component
{
    /** Secao ativa: 'home', 'ranking', 'team' ou o slug do curso aberto. */
    public string $active = 'home';

    public ?string $activeLesson = null;

    /** @var array<string, bool> cursos com a lista de aulas aberta */
    public array $open = [];

    public function mount(): void
    {
        $path = trim((string) request()->path(), '/');
        $this->active = match (true) {
            str_contains($path, 'academia/curso/') => (string) request()->route('slug'),
            str_ends_with($path, 'academia/ranking') => 'ranking',
            str_ends_with($path, 'academia/equipe') => 'team',
            default => 'home',
        };
        $this->activeLesson = request()->query('aula');

        if (! in_array($this->active, ['home', 'ranking', 'team'], true)) {
            $this->open[$this->active] = true;
        }
    }

    public function toggle(string $slug): void
    {
        $this->open[$slug] = ! ($this->open[$slug] ?? false);
    }

    #[On('academy-updated')]
    public function refresh(): void
    {
        // so' re-renderiza
    }

    #[On('academy-lesson')]
    public function lessonChanged(string $slug, string $lessonId): void
    {
        $this->active = $slug;
        $this->activeLesson = $lessonId;
        $this->open[$slug] = true;
    }

    public function render()
    {
        $user = auth()->user();
        $courses = app(AcademyOverview::class)->courses($user);

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

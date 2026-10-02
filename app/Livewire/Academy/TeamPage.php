<?php

namespace App\Livewire\Academy;

use App\Models\User;
use App\Services\AcademyParticipation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Participacao da equipe -- so' ADMIN do cliente. O cliente (tenant) vem SEMPRE do usuario
 * logado, nunca de parametro da tela.
 */
#[Layout('academy.layout')]
class TeamPage extends Component
{
    #[Url(as: 'periodo')]
    public string $period = '30';

    public string $search = '';

    #[Url(as: 'situacao')]
    public string $status = '';

    public string $sort = 'points';

    public string $dir = 'desc';

    public int $page = 1;

    private const SORTABLE = ['name', 'points', 'lessons_done', 'certificates', 'minutes', 'last_activity', 'completion', 'quiz_rate'];

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() && $user->tenant_id, 403);
    }

    public function updating($name): void
    {
        if (in_array($name, ['period', 'search', 'status'], true)) {
            $this->page = 1;
        }
    }

    public function sortBy(string $column): void
    {
        abort_unless(in_array($column, self::SORTABLE, true), 422);
        $this->dir = $this->sort === $column && $this->dir === 'desc' ? 'asc' : 'desc';
        $this->sort = $column;
        $this->page = 1;
    }

    public function go(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function render()
    {
        $tenantId = (string) auth()->user()->tenant_id;
        $service = app(AcademyParticipation::class);
        $since = $service->since($this->period);

        $query = User::query()->withoutGlobalScopes()->fromSub($service->usersQuery($tenantId, $since), 'users');

        if ($this->search !== '') {
            $query->where('name', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], $this->search).'%');
        }
        match ($this->status) {
            'ativos' => $query->where(fn ($q) => $q->where('points', '>', 0)->orWhere('lessons_done', '>', 0)),
            'inativos' => $query->where('points', 0)->where('lessons_done', 0),
            'certificados' => $query->where('certificates', '>', 0),
            default => null,
        };

        $sort = in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'points';
        $dir = $this->dir === 'asc' ? 'asc' : 'desc';
        match ($sort) {
            'completion' => $query->orderByRaw("(case when lessons_available > 0 then lessons_done_all::float / lessons_available else 0 end) {$dir}"),
            'quiz_rate' => $query->orderByRaw("(case when quiz_total > 0 then quiz_correct::float / quiz_total else -1 end) {$dir}"),
            default => $query->orderBy($sort, $dir),
        };
        $query->orderBy('name');

        $perPage = 15;
        $total = (clone $query)->toBase()->getCountForPagination();
        $rows = $query->forPage($this->page, $perPage)->get();

        return view('livewire.academy.team', [
            'rows' => $rows,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'summary' => $service->summary($tenantId, $since),
            'courses' => $service->courses($tenantId, $since),
            'weekly' => $service->weekly($tenantId),
            'periods' => AcademyParticipation::PERIODS,
        ]);
    }
}

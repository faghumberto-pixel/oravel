<?php

namespace App\Filament\Central\Pages;

use App\Services\AcademyClientReport;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Quadro por cliente da participação na Academia e nos testes de treinamento:
 * acessos mês a mês e dia a dia, cursos, exercícios e provas (com acertos).
 */
class ParticipacaoTreinamento extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Participação no Treinamento';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $title = 'Participação no Treinamento, por cliente';

    protected static ?int $navigationSort = 89;

    protected static string $view = 'filament.central.pages.participacao-treinamento';

    public const PERIODOS = [
        'mes' => 'Mês atual',
        '7' => 'Últimos 7 dias',
        '30' => 'Últimos 30 dias',
        '90' => 'Últimos 90 dias',
        '365' => 'Últimos 12 meses',
        'todo' => 'Todo o período',
    ];

    public string $period = '30';

    public ?string $tenantId = null;

    public ?string $selectedTenantId = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public function select(string $tenantId): void
    {
        $this->selectedTenantId = $this->selectedTenantId === $tenantId ? null : $tenantId;
    }

    public function updated(string $property): void
    {
        if ($property === 'tenantId' || $property === 'period') {
            $this->selectedTenantId = null;
        }
    }

    public function since(): ?Carbon
    {
        return match ($this->period) {
            'mes' => now()->startOfMonth(),
            'todo' => null,
            default => now()->subDays((int) $this->period)->startOfDay(),
        };
    }

    private function report(): AcademyClientReport
    {
        return app(AcademyClientReport::class);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function clients(): Collection
    {
        return $this->report()->clients($this->since(), $this->tenantId)
            ->sortByDesc(fn ($c) => [$c['acessos'], $c['provas']])->values();
    }

    /** @param Collection<int, array<string, mixed>> $clients */
    public function totals(Collection $clients): array
    {
        $provasTotal = (int) $clients->sum('provas_total');

        return [
            'clientes' => $clients->count(),
            'com_participacao' => $clients->where('participantes', '>', 0)->count(),
            'acessos' => (int) $clients->sum('acessos'),
            'cursos' => (int) $clients->sum('cursos'),
            'exercicios' => (int) $clients->sum('exercicios'),
            'provas' => (int) $clients->sum('provas'),
            'nota' => $provasTotal ? round($clients->sum('provas_acertos') / $provasTotal * 10, 1) : null,
        ];
    }

    public function monthly(): array
    {
        return $this->report()->monthly(12, $this->tenantId);
    }

    public function daily(): Collection
    {
        return $this->report()->daily(30, $this->selectedTenantId ?: $this->tenantId);
    }

    public function detail(): ?array
    {
        if (! $this->selectedTenantId) {
            return null;
        }

        $cliente = $this->clients()->firstWhere('tenant_id', $this->selectedTenantId);

        return $cliente ? ['cliente' => $cliente, 'users' => $this->report()->users($this->selectedTenantId, $this->since())] : null;
    }

    public function publicTests(): Collection
    {
        return $this->report()->publicTests($this->since());
    }
}

<?php

namespace App\Filament\Central\Resources\CourseResource\Pages;

use App\Filament\Central\Resources\CourseResource;
use App\Filament\Resources\CourseResource\Pages\Team;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AcademyParticipation;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * Participacao dos colaboradores na Academia, de TODOS os clientes (visao da operacao Oravel),
 * com filtro de periodo e de cliente. Mesmos calculos da tela do admin do cliente.
 */
class AcademyPointsReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CourseResource::class;

    protected static string $view = 'filament.central.academy-points';

    protected static ?string $title = 'Academia: participação dos clientes';

    public string $period = '30';

    public ?string $tenantId = null;

    public function tenantOptions(): array
    {
        return Tenant::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id')->all();
    }

    private function tenant(): ?string
    {
        return $this->tenantId ?: null;
    }

    private function since()
    {
        return app(AcademyParticipation::class)->since($this->period);
    }

    public function summary(): array
    {
        return app(AcademyParticipation::class)->summary($this->tenant(), $this->since());
    }

    public function courses(): Collection
    {
        return app(AcademyParticipation::class)->courses($this->tenant(), $this->since());
    }

    public function weekly(): array
    {
        return app(AcademyParticipation::class)->weekly($this->tenant());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => User::query()->withoutGlobalScopes()->fromSub(
                app(AcademyParticipation::class)->usersQuery($this->tenant(), $this->since()), 'users'
            ))
            ->defaultSort('points', 'desc')
            ->columns(Team::columns(true))
            ->filters(Team::filters())
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Nenhum colaborador encontrado');
    }
}

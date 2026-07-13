<?php

namespace App\Filament\Pages;

use App\Models\Appointment;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class AgendaTecnico extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Programação';

    protected static ?string $title = 'Programação';

    // Sem grupo de proposito: autoatendimento (qualquer usuario ve a propria
    // agenda, nao depende do plano ter "Ordens de Servico"), entao nao faz
    // sentido aparecer sob o rotulo "Manutencao" -- isso sugeriria que o
    // tenant tem o modulo comercial de Manutencao quando pode nao ter.
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.agenda-tecnico';

    public string $technicianId = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Appointment::class);
    }

    /**
     * Admin ve todo mundo. Quem supervisiona algum setor (roles.department_id,
     * ver RoleResource) ve so os usuarios daquele(s) setor(es) -- nao o
     * tenant inteiro. Sem privilegio nenhum, a view nem chega a chamar isso
     * (dropdown fica escondido, ver getCanViewAllProperty()).
     */
    public function getTechniciansProperty(): Collection
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return collect();
        }

        $user = auth()->user();
        $query = User::where('tenant_id', $tenant->id);

        if (! $user->isAdmin()) {
            $query->whereIn('department_id', $user->supervisedDepartmentIds());
        }

        return $query->orderBy('name')->pluck('name', 'id');
    }

    public function getCanViewAllProperty(): bool
    {
        $user = auth()->user();

        return $user->isAdmin() || ! empty($user->supervisedDepartmentIds());
    }
}

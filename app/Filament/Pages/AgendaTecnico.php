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
    protected static ?string $navigationGroup = 'Manutenção';
    protected static ?string $navigationLabel = 'Programação';

    protected static ?string $title = 'Programação';

    // Cada departamento tem sua propria Programacao, sem misturar (Logistica
    // tem a dela, ver ProgramacaoLogistica). Esta e' a de Manutencao --
    // Agendamento pessoal + O.S., nada de mobilizacao/desmobilizacao aqui.
    //
    // Técnicos comuns (não-admin) são redirecionados para a versão mobile
    // (AgendaTecnicoMobile) que é otimizada para campo. Admins/supervisores
    // veem o calendário full com saade/filament-fullcalendar.

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.agenda-tecnico';

    public string $technicianId = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Appointment::class);
    }

    public function mount(): void
    {
        $user = auth()->user();

        // Técnicos comuns são redirecionados para mobile
        if (! $user->isAdmin() && empty($user->supervisedDepartmentIds())) {
            $this->redirect(route('agenda-tecnico.mobile'), navigate: true);
        }
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

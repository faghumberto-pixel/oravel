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

    public function getTechniciansProperty(): Collection
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return collect();
        }

        return User::where('tenant_id', $tenant->id)->orderBy('name')->pluck('name', 'id');
    }
}

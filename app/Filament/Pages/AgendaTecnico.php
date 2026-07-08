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

    protected static ?string $navigationGroup = 'Manutenção';

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

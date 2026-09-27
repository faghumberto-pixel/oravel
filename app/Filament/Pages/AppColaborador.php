<?php

namespace App\Filament\Pages;

use App\Models\Absence;
use App\Models\TimeClock;
use Filament\Pages\Page;
use Livewire\Attributes\Layout;

/**
 * Ponto de entrada único do colaborador (pedido do usuário 27/09/2026:
 * "um link só, no qual lá terá os botões para cada uma das telas").
 * Não depende de TechnicianDailyTasks (route('filament.admin.pages.
 * technician-daily-tasks') está redirecionando pra painel-controle desde
 * 2026-09-02, ver routes/web.php -- "módulo Tarefas travado com modal
 * offline") -- essa pagina fica isolada de propósito.
 */
#[Layout('layouts.checklist-mobile')]
class AppColaborador extends Page
{
    protected static string $view = 'filament.pages.app-colaborador';

    protected static ?string $slug = 'app-colaborador';

    protected static ?string $title = 'App do Colaborador';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user();
    }

    public function podeVerPonto(): bool
    {
        return (bool) auth()->user()?->can('viewAny', TimeClock::class);
    }

    public function podeVerFaltas(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Absence::class);
    }
}

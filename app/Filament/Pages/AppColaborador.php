<?php

namespace App\Filament\Pages;

use App\Models\Absence;
use App\Models\MaintenanceOrder;
use App\Models\TimeClock;
use Filament\Pages\Page;
use Livewire\Attributes\Layout;

/**
 * Ponto de entrada único do colaborador (pedido do usuário 27/09/2026:
 * "um link só, no qual lá terá os botões para cada uma das telas").
 * Independente de TechnicianDailyTasks de propósito (a rota dessa página
 * ficou redirecionada pra painel-controle entre 02/09 e 27/09/2026 --
 * ver commit 0e54ecd, "módulo Tarefas travado com modal offline". Achado
 * ao reativar 27/09: o diagnóstico original era vago (autoria Claude
 * Haiku, já banido deste projeto por instabilidade -- ver memória) e o
 * bug reproduzível de verdade era um erro de JS nos filtros da própria
 * tela (`$filterType` em vez de `$wire.filterType`, Alpine), já
 * corrigido. Não foi encontrado nenhum modal travando no wizard em si;
 * reativado com essa ressalva registrada).
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

    public function podeVerOrdens(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
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

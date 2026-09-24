<?php

namespace App\Filament\Pages;

use App\Support\Tenancy;
use Filament\Pages\Page;

/**
 * Programacao de Logistica: mobilizacao/desmobilizacao agendada, separada
 * da Programacao de Manutencao (AgendaTecnico) -- cada departamento tem a
 * sua, sem misturar tipos de evento entre elas.
 */
class ProgramacaoLogistica extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationParentItem = 'Pátio';

    protected static ?string $navigationLabel = 'Programação';

    protected static ?string $title = 'Programação — Logística';

    protected static string $view = 'filament.pages.programacao-logistica';

    // Achado em simulação real 2026-09-24: checava só cargo/departamento,
    // nunca o Contrato -- adicionado hasFeature() como condição extra, sem
    // tirar a checagem de cargo que já existia.
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user
            && ($user->isAdmin() || ! empty($user->supervisedDepartmentIds()))
            && (bool) Tenancy::current()?->hasFeature('tabela_equipment_movements');
    }
}

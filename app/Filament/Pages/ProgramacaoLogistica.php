<?php

namespace App\Filament\Pages;

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

    protected static ?string $navigationLabel = 'Programação';

    protected static ?string $title = 'Programação — Logística';

    protected static string $view = 'filament.pages.programacao-logistica';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user && ($user->isAdmin() || ! empty($user->supervisedDepartmentIds()));
    }
}

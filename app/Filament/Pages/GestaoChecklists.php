<?php

namespace App\Filament\Pages;

use App\Models\ChecklistTemplate;
use App\Models\CkExecution;
use App\Models\FrotaChecklist;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

/**
 * Page "hub" (sem dados proprios) que serve so' de item pai do submenu "Checklist" no menu Ativos -- mesmo padrao de GestaoAtivos.
 * Diferente dos outros hubs, aparece so' se o usuario puder abrir ao menos um filho (checklists de equipamento, de veiculos da Frota
 * ou os antigos): sem pai, os filhos some junto, e submenu vazio so' confundiria.
 */
class GestaoChecklists extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Ativos';

    protected static ?string $navigationLabel = 'Checklist';

    protected static ?string $title = 'Checklist';

    protected static string $view = 'filament.pages.gestao-checklists';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return auth()->check() && collect([CkExecution::class, FrotaChecklist::class, ChecklistTemplate::class])->contains(fn (string $m) => Gate::allows('viewAny', $m));
    }
}

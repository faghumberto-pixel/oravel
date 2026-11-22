<?php

namespace App\Filament\Pages;

use App\Domain\Intelligence\Concerns\UsaResumoDoIntelligence;
use Filament\Pages\Page;

/** Intelligence › Assistente de IA. Chave de contrato: IntelligenceAssistente0. */
class IntelligenceAssistente extends Page
{
    use UsaResumoDoIntelligence;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Intelligence';

    protected static ?string $navigationLabel = 'Assistente de IA';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Assistente de IA';

    protected static ?string $slug = 'intelligence-ia';

    protected static string $view = 'filament.pages.intelligence-assistente';
}

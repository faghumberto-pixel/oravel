<?php

namespace App\Filament\Pages;

use App\Domain\Intelligence\Concerns\UsaResumoDoIntelligence;
use App\Domain\Intelligence\Services\EvolucaoService;
use App\Domain\Intelligence\Support\Graficos;
use App\Support\Tenancy;
use Filament\Pages\Page;

/** Intelligence › Comparação interna: a empresa contra ela mesma, em duas fotografias à escolha. Chave de contrato: menu_intelligence_comparacao. */
class IntelligenceComparacao extends Page
{
    use UsaResumoDoIntelligence;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Intelligence';

    protected static ?string $navigationLabel = 'Comparação interna';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Comparação interna';

    protected static ?string $slug = 'intelligence-comparacao';

    protected static string $view = 'filament.pages.intelligence-comparacao';

    public ?string $de = null;

    public ?string $para = null;

    public function mount(): void
    {
        $opcoes = array_keys($this->opcoes());
        $this->para = $opcoes[0] ?? null;
        $this->de = $opcoes[1] ?? null;
    }

    /** @return array<string, string> id => data */
    public function opcoes(): array
    {
        $t = Tenancy::current();

        return $t ? app(EvolucaoService::class)->opcoes($t->id) : [];
    }

    /** @return ?array{de: string, para: string, areas: list<array>, indicadores: list<array>} */
    public function comparacao(): ?array
    {
        $t = Tenancy::current();

        return ($t && $this->de && $this->para && $this->de !== $this->para) ? app(EvolucaoService::class)->comparar($t->id, $this->de, $this->para) : null;
    }

    /** @return array<string, ?array> */
    public function graficos(array $comparacao): array
    {
        $g = app(Graficos::class);

        return ['radar' => $g->comparacaoRadar($comparacao), 'barras' => $g->comparacaoBarras($comparacao)];
    }
}

<?php

namespace App\Filament\Pages;

use App\Domain\Intelligence\Models\IntelligenceEquipamento as Equipamento;
use App\Domain\Intelligence\Services\EquipamentosService;
use App\Domain\Intelligence\Support\Catalogo;
use App\Domain\Intelligence\Support\Graficos;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * Intelligence › Equipamento (detalhe, sem item de menu): um equipamento com o motivo da classificação, os números do mês e a evolução
 * fotografia a fotografia. Acesso pelas mesmas chaves da tela "Equipamentos".
 */
class IntelligenceEquipamento extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'intelligence-equipamento';

    protected static ?string $title = 'Equipamento';

    protected static string $view = 'filament.pages.intelligence-equipamento';

    #[Url]
    public string $patrimonio = '';

    public static function canAccess(): bool
    {
        $u = auth()->user();
        $t = Tenancy::current();

        return (bool) ($u?->can('viewAny', Equipamento::class) && $t?->hasFeature('menu_intelligence_equipamentos'));
    }

    public function mount(): void
    {
        abort_unless($this->dados() !== null, 404);
    }

    public function getTitle(): string
    {
        return $this->patrimonio !== '' ? 'Equipamento '.mb_strtoupper($this->patrimonio) : 'Equipamento';
    }

    /** @return ?array{atual: array<string, mixed>, historico: list<array<string, mixed>>} */
    public function dados(): ?array
    {
        $t = Tenancy::current()?->id;

        return ($t && $this->patrimonio !== '') ? app(EquipamentosService::class)->equipamento($t, $this->patrimonio) : null;
    }

    /** @return array<string, ?array> */
    public function graficos(array $historico): array
    {
        return (new Graficos)->equipamentoHistorico($historico);
    }

    /** @return list<string> */
    public function acoes(string $classe): array
    {
        return Catalogo::acoesPorClasse()[$classe] ?? [];
    }

    public function urlLista(): string
    {
        return IntelligenceEquipamentos::getUrl();
    }
}

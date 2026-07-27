<?php

namespace App\Livewire;

use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Busca de tela/menu (nao de registro) -- o global search nativo do
 * Filament so' aparece quando algum Resource declara
 * getGloballySearchableAttributes() (nenhum declara aqui hoje, ver
 * FilamentManager::isGlobalSearchEnabled()), entao a lupa nunca renderiza.
 * Pedido do usuario (2026-07-26): achar QUALQUER tela do sistema (ex:
 * "Planta Baixa"), nao um registro -- por isso um componente proprio, que
 * varre Resources+Pages descobertos pelo painel (respeitando
 * canViewAny()/canAccess()) em vez de depender do gate de busca de
 * registros do Filament.
 */
class ScreenSearch extends Component
{
    public string $query = '';

    public bool $open = false;

    /**
     * @return Collection<int, array{label: string, url: string}>
     */
    public function getResultsProperty(): Collection
    {
        $needle = trim($this->query);

        if ($needle === '') {
            return collect();
        }

        $needle = Str::of($needle)->lower()->ascii()->toString();

        $itens = collect();

        foreach (Filament::getResources() as $resource) {
            try {
                if (! $resource::canViewAny()) {
                    continue;
                }

                $label = $resource::getNavigationLabel();

                if (! Str::contains(Str::of($label)->lower()->ascii()->toString(), $needle)) {
                    continue;
                }

                $itens->push(['label' => $label, 'url' => $resource::getUrl()]);
            } catch (\Throwable) {
                continue;
            }
        }

        foreach (Filament::getPages() as $page) {
            try {
                if (! $page::canAccess()) {
                    continue;
                }

                $label = $page::getNavigationLabel();

                if (! Str::contains(Str::of($label)->lower()->ascii()->toString(), $needle)) {
                    continue;
                }

                $itens->push(['label' => $label, 'url' => $page::getUrl()]);
            } catch (\Throwable) {
                continue;
            }
        }

        return $itens->unique('label')->sortBy('label')->take(10)->values();
    }

    public function render()
    {
        return view('livewire.screen-search');
    }
}

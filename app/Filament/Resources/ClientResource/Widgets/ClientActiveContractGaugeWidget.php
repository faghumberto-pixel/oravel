<?php

namespace App\Filament\Resources\ClientResource\Widgets;

use App\Filament\Widgets\Charts\GaugeChart;
use App\Models\Client;
use App\Support\Tenancy;

/**
 * Ponte entre o GaugeChart genérico (sem query interna, por design) e o
 * registro de widgets do cabeçalho da listagem
 * (ListClients::getHeaderWidgets()).
 */
class ClientActiveContractGaugeWidget extends GaugeChart
{
    /**
     * Todo Filament\Widgets\Widget é lazy por padrão (Filament\Support\
     * Concerns\CanBeLazy, $isLazy=true) -- getHeaderWidgets() carrega o
     * conteúdo real via uma requisição Livewire separada depois do
     * placeholder inicial. Desligado aqui: o gráfico é leve (1 query),
     * não precisa do adiamento, e lazy deixava impossível confirmar via
     * teste HTTP simples se o gráfico realmente aparece.
     */
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    /**
     * Assinatura repete a de GaugeChart::mount() de propósito (parâmetros
     * nunca usados) -- PHP não deixa subclasse declarar mount() com
     * assinatura mais restrita que a do pai.
     */
    public function mount(
        float $value = 0,
        ?float $target = null,
        ?string $chartTitle = null,
        ?array $bands = null,
        ?string $needleColor = null,
    ): void {
        $total = Client::count();
        $comContratoAtivo = Client::whereHas('contracts', fn ($q) => $q->where('status', 'Ativo'))->count();

        parent::mount(
            value: $total > 0 ? round(($comContratoAtivo / $total) * 100, 1) : 0.0,
            target: 60,
            chartTitle: 'Taxa de Clientes com Contrato Ativo',
        );
    }
}

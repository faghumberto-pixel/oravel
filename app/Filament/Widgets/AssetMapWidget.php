<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use Filament\Widgets\Widget;

class AssetMapWidget extends Widget
{
    // Aponta para a view customizada do mapa
    protected static string $view = 'filament.widgets.asset-map-widget';
    
    // Define explicitamente que este widget deve ocupar toda a largura da tela (Rodapé do Dashboard)
    protected int | string | array $columnSpan = 'full';

    // Método para buscar os ativos e enviar para a view com segurança (Tenant Isolation)
    public function getAssets()
    {
        return Asset::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('tenant_id', auth()->user()->tenant_id)
            ->get();
    }
}
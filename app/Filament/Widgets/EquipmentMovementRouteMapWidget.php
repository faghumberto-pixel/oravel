<?php

namespace App\Filament\Widgets;

use App\Models\EquipmentMovement;
use Filament\Widgets\Widget;

/**
 * Rota percorrida por uma movimentacao, plotada com os checkpoints
 * manuais (equipment_movement_locations) em ordem cronologica -- mesmo
 * padrao Leaflet + OpenStreetMap ja usado no Mapa de Leads do CRM
 * (CrmLeadMapWidget), sem custo de API paga.
 */
class EquipmentMovementRouteMapWidget extends Widget
{
    protected static string $view = 'filament.widgets.equipment-movement-route-map-widget';

    protected int|string|array $columnSpan = 'full';

    public ?EquipmentMovement $equipmentMovement = null;

    protected static $checkpointLabels = [
        'saida_patio' => 'Saída do Pátio',
        'checkpoint' => 'Checkpoint',
        'chegada_destino' => 'Chegada no Destino',
        'saida_cliente' => 'Saída do Cliente',
        'chegada_patio' => 'Chegada no Pátio',
    ];

    public function getPoints(): array
    {
        if (! $this->equipmentMovement) {
            return [];
        }

        return $this->equipmentMovement->locations
            ->map(fn ($location) => [
                'lat' => (float) $location->latitude,
                'lng' => (float) $location->longitude,
                'label' => static::$checkpointLabels[$location->checkpoint_type] ?? $location->checkpoint_type,
                'address' => $location->address,
                'captured_at' => optional($location->captured_at)->format('d/m/Y H:i'),
            ])
            ->values()
            ->all();
    }
}

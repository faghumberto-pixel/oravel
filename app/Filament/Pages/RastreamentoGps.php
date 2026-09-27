<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TraccarDeviceResource;
use App\Models\TraccarDevice;
use App\Support\TraccarService;
use Filament\Actions;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * Mapa ao vivo (Leaflet + OpenStreetMap, mesmo padrao de MapaEquipamentos)
 * com as posicoes atuais dos devices Traccar vinculados no tenant. O vinculo
 * device<->usuario continua em TraccarDeviceResource; esta pagina so le.
 */
class RastreamentoGps extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationParentItem = 'Frota';

    protected static ?string $navigationLabel = 'Rastreamento GPS';

    protected static ?string $title = 'Rastreamento GPS';

    protected static ?string $slug = 'rastreamento-gps';

    protected static string $view = 'filament.pages.rastreamento-gps';

    public array $positions = [];

    public function mount(): void
    {
        $this->refreshPositions();
    }

    public static function canAccess(): bool
    {
        return TraccarDeviceResource::canViewAny();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('gerenciarVinculos')
                ->label('Gerenciar vínculos')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn () => TraccarDeviceResource::getUrl()),
        ];
    }

    /**
     * Chamado pelo wire:poll da view e no mount(). Dispara um evento
     * de browser em vez de deixar o Livewire re-renderizar o bloco do
     * mapa (que fica wire:ignore -- o Leaflet so' e inicializado uma vez).
     */
    public function refreshPositions(): void
    {
        $this->positions = $this->buildPositions();

        $this->dispatch('positions-updated', positions: $this->positions);
    }

    private function buildPositions(): array
    {
        $devices = TraccarDevice::query()->with('user')->get();

        if ($devices->isEmpty()) {
            return [];
        }

        $raw = app(TraccarService::class)->getPositions(
            $devices->pluck('traccar_device_id')->all()
        );

        $byDeviceId = collect($raw)->keyBy('deviceId');

        return $devices
            ->map(function (TraccarDevice $device) use ($byDeviceId) {
                $position = $byDeviceId->get($device->traccar_device_id);

                if (! $position || blank($position['latitude'] ?? null)) {
                    return null;
                }

                return [
                    'id' => (string) $device->id,
                    'label' => $device->user?->name ?? $device->identifier,
                    'latitude' => $position['latitude'],
                    'longitude' => $position['longitude'],
                    // Traccar retorna velocidade em nos (knots).
                    'speed' => round(($position['speed'] ?? 0) * 1.852, 1),
                    'updated_at' => isset($position['fixTime'])
                        ? Carbon::parse($position['fixTime'])->format('d/m/Y H:i')
                        : null,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }
}

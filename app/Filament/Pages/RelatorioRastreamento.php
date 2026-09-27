<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TraccarDeviceResource;
use App\Models\TraccarDevice;
use App\Support\TraccarService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * Relatorio de distancia percorrida por vendedor/tecnico num periodo
 * (TraccarService::calculateDistanceKm) + rota no mapa sob demanda
 * (TraccarService::getRoute, so' quando o usuario clica "Ver rota" --
 * evita chamar o Traccar pra todo mundo de uma vez).
 */
class RelatorioRastreamento extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationParentItem = 'Frota';

    protected static ?string $navigationLabel = 'Relatório de Rastreamento';

    protected static ?string $title = 'Relatório de Rastreamento';

    protected static ?string $slug = 'relatorio-rastreamento';

    protected static string $view = 'filament.pages.relatorio-rastreamento';

    public ?array $data = [];

    public array $rows = [];

    public ?string $routeDeviceId = null;

    public array $route = [];

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
        ]);

        $this->generateReport();
    }

    public static function canAccess(): bool
    {
        return TraccarDeviceResource::canViewAny();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('from')->label('De')->native(false)->required(),
                DatePicker::make('to')->label('Até')->native(false)->required(),
            ])
            ->statePath('data')
            ->columns(2);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gerarRelatorio')
                ->label('Gerar relatório')
                ->icon('heroicon-o-magnifying-glass')
                ->action('generateReport'),

            Action::make('gerenciarVinculos')
                ->label('Gerenciar vínculos')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn () => TraccarDeviceResource::getUrl()),
        ];
    }

    public function generateReport(): void
    {
        $state = $this->form->getState();
        $from = Carbon::parse($state['from'])->startOfDay();
        $to = Carbon::parse($state['to'])->endOfDay();

        $service = app(TraccarService::class);

        $this->rows = TraccarDevice::query()
            ->with('user')
            ->get()
            ->map(fn (TraccarDevice $device) => [
                'id' => (string) $device->id,
                'label' => $device->user?->name ?? $device->identifier,
                'distance_km' => $service->calculateDistanceKm($device->traccar_device_id, $from, $to),
            ])
            ->values()
            ->toArray();

        $this->routeDeviceId = null;
        $this->route = [];
    }

    public function verRota(string $deviceId): void
    {
        $device = TraccarDevice::query()->find($deviceId);

        if (! $device) {
            return;
        }

        $state = $this->form->getState();
        $from = Carbon::parse($state['from'])->startOfDay();
        $to = Carbon::parse($state['to'])->endOfDay();

        $raw = app(TraccarService::class)->getRoute($device->traccar_device_id, $from, $to);

        $this->routeDeviceId = $deviceId;

        $this->route = collect($raw)
            ->filter(fn (array $point) => filled($point['latitude'] ?? null))
            ->map(fn (array $point) => [
                'lat' => $point['latitude'],
                'lng' => $point['longitude'],
            ])
            ->values()
            ->toArray();
    }
}

<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit.prevent="generateReport">
            {{ $this->form }}
        </form>
    </x-filament::section>

    <x-filament::section heading="Distância percorrida no período">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2 font-medium text-gray-500 dark:text-gray-400">Vendedor/Técnico</th>
                        <th class="py-2 font-medium text-gray-500 dark:text-gray-400">Distância</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 text-gray-900 dark:text-gray-100">{{ $row['label'] }}</td>
                            <td class="py-2 text-gray-900 dark:text-gray-100">{{ number_format($row['distance_km'], 2, ',', '.') }} km</td>
                            <td class="py-2 text-right">
                                <x-filament::button size="sm" color="gray" wire:click="verRota('{{ $row['id'] }}')">
                                    Ver rota
                                </x-filament::button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-4 text-gray-500 dark:text-gray-400">
                                Nenhum device vinculado, ou nenhum dado disponível no período.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    @if ($routeDeviceId)
        <x-filament::section heading="Rota no período">
            <style>
                @import url('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
            </style>

            <div
                wire:ignore
                wire:key="rota-mapa-{{ $routeDeviceId }}"
                x-data="rotaRastreamentoMap({{ Illuminate\Support\Js::from($route) }})"
            >
                <div x-ref="mapEl" style="height: 500px; width: 100%; border-radius: 8px; z-index: 1;"></div>
            </div>

            @if (empty($route))
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-4">
                    Nenhuma posição registrada para este device no período selecionado.
                </p>
            @endif
        </x-filament::section>
    @endif

    <script>
        function rotaRastreamentoMap(initialRoute) {
            return {
                map: null,
                line: null,

                init() {
                    if (typeof L === 'undefined') {
                        let script = document.createElement('script');
                        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                        document.head.appendChild(script);
                        script.onload = () => this.start(initialRoute);
                    } else {
                        this.start(initialRoute);
                    }
                },

                start(route) {
                    this.map = L.map(this.$refs.mapEl).setView([-22.9056, -47.0608], 12);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap',
                    }).addTo(this.map);

                    if (route.length) {
                        const latlngs = route.map((p) => [p.lat, p.lng]);
                        this.line = L.polyline(latlngs, { color: '#f97316' }).addTo(this.map);
                        this.map.fitBounds(this.line.getBounds());
                    }

                    setTimeout(() => this.map.invalidateSize(), 300);
                },
            };
        }
    </script>
</x-filament-panels::page>

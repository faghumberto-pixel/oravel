<x-filament-panels::page>
    <style>
        @import url('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
    </style>

    <div wire:poll.20s="refreshPositions">
        <x-filament::section>
            <div
                wire:ignore
                x-data="rastreamentoGpsMap({{ Illuminate\Support\Js::from($positions) }})"
            >
                <div x-ref="mapEl" style="height: 600px; width: 100%; border-radius: 8px; z-index: 1;"></div>
            </div>

            @if (empty($positions))
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-4">
                    Nenhum device com posição disponível no momento. Verifique se há devices vinculados em "Gerenciar vínculos".
                </p>
            @endif
        </x-filament::section>
    </div>

    <script>
        function rastreamentoGpsMap(initialPositions) {
            return {
                map: null,
                markers: {},

                init() {
                    if (typeof L === 'undefined') {
                        let script = document.createElement('script');
                        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                        document.head.appendChild(script);
                        script.onload = () => this.start(initialPositions);
                    } else {
                        this.start(initialPositions);
                    }

                    this.$wire.on('positions-updated', (data) => this.updateMarkers(data.positions ?? []));
                },

                start(positions) {
                    this.map = L.map(this.$refs.mapEl).setView([-22.9056, -47.0608], 11);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap',
                    }).addTo(this.map);

                    this.updateMarkers(positions);

                    setTimeout(() => this.map.invalidateSize(), 500);
                },

                updateMarkers(positions) {
                    if (!this.map) {
                        return;
                    }

                    const seenIds = [];

                    positions.forEach((position) => {
                        if (!position.latitude || !position.longitude) {
                            return;
                        }

                        seenIds.push(position.id);

                        const popup = '<b>' + position.label + '</b><br>'
                            + position.speed + ' km/h<br>'
                            + (position.updated_at ?? '');

                        if (this.markers[position.id]) {
                            this.markers[position.id].setLatLng([position.latitude, position.longitude]);
                            this.markers[position.id].setPopupContent(popup);
                        } else {
                            this.markers[position.id] = L.marker([position.latitude, position.longitude])
                                .addTo(this.map)
                                .bindPopup(popup);
                        }
                    });

                    Object.keys(this.markers).forEach((id) => {
                        if (!seenIds.includes(id)) {
                            this.map.removeLayer(this.markers[id]);
                            delete this.markers[id];
                        }
                    });
                },
            };
        }
    </script>
</x-filament-panels::page>

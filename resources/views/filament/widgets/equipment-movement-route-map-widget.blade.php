<div>
    <x-filament-widgets::widget>
        <x-filament::section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Rota do Transporte</h2>
                <span class="text-xs text-gray-500">{{ count($this->getPoints()) }} checkpoint(s) registrado(s)</span>
            </div>

            <style>
                @import url('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
            </style>

            @if(count($this->getPoints()) === 0)
                <p class="text-xs text-gray-500">Nenhum checkpoint de localização registrado ainda para esta movimentação.</p>
            @else
                <div wire:ignore>
                    <div
                        x-data="{
                            mapa: null,
                            pontos: {{ json_encode($this->getPoints()) }},

                            init() {
                                if (typeof L === 'undefined') {
                                    let script = document.createElement('script');
                                    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                                    document.head.appendChild(script);
                                    script.onload = () => this.renderizarMapa();
                                } else {
                                    this.renderizarMapa();
                                }
                            },

                            renderizarMapa() {
                                this.mapa = L.map(this.$refs.mapaRota).setView([this.pontos[0].lat, this.pontos[0].lng], 10);

                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                    attribution: '© OpenStreetMap'
                                }).addTo(this.mapa);

                                let linha = [];

                                this.pontos.forEach((ponto, index) => {
                                    let popup = '<b>' + (index + 1) + '. ' + ponto.label + '</b>'
                                        + (ponto.captured_at ? '<br>' + ponto.captured_at : '')
                                        + (ponto.address ? '<br><span style=\'color:#6b7280\'>' + ponto.address + '</span>' : '');

                                    L.marker([ponto.lat, ponto.lng]).addTo(this.mapa).bindPopup(popup);
                                    linha.push([ponto.lat, ponto.lng]);
                                });

                                if (linha.length > 1) {
                                    L.polyline(linha, { color: '#E8541A', weight: 3 }).addTo(this.mapa);
                                }

                                this.mapa.fitBounds(linha, { padding: [30, 30] });

                                setTimeout(() => this.mapa.invalidateSize(), 500);
                            }
                        }"
                    >
                        <div x-ref="mapaRota" style="height: 400px; width: 100%; border-radius: 8px; z-index: 1;"></div>
                    </div>
                </div>

                <ol class="mt-3 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                    @foreach($this->getPoints() as $index => $ponto)
                        <li>{{ $index + 1 }}. {{ $ponto['label'] }} — {{ $ponto['captured_at'] }}</li>
                    @endforeach
                </ol>
            @endif
        </x-filament::section>
    </x-filament-widgets::widget>
</div>

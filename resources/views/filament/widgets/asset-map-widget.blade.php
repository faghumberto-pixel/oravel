<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Geolocalização da Frota</h2>
        </div>

        <style>
            @import url('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
        </style>

        <div wire:ignore>
            <div 
                x-data="{
                    mapa: null,
                    ativos: {{ json_encode($this->getAssets()) }},
                    
                    init() {
                        // Baixa a biblioteca apenas se não existir
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
                        // Conecta à div x-ref='meuMapa'
                        this.mapa = L.map(this.$refs.meuMapa).setView([-22.9056, -47.0608], 10);
                        
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '© OpenStreetMap'
                        }).addTo(this.mapa);

                        // Desenha os marcadores do banco
                        this.ativos.forEach(ativo => {
                            if (ativo.latitude && ativo.longitude) {
                                let lat = parseFloat(ativo.latitude);
                                let lng = parseFloat(ativo.longitude);
                                let nome = ativo.name ? ativo.name : 'Ativo sem nome';
                                let pat = ativo.patrimonio ? ativo.patrimonio : 'N/A';
                                
                                L.marker([lat, lng])
                                    .addTo(this.mapa)
                                    .bindPopup('<b>' + nome + '</b><br>Patrimônio: ' + pat);
                            }
                        });

                        // Força o ajuste do tamanho para evitar blocos cinzas
                        setTimeout(() => this.mapa.invalidateSize(), 500);
                    }
                }"
            >
                <div x-ref="meuMapa" style="height: 450px; width: 100%; border-radius: 8px; z-index: 1;"></div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
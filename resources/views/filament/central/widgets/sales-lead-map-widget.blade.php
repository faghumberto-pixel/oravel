<div>
    <x-filament-widgets::widget>
        <x-filament::section>
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Mapa Comercial — Prospects</h2>
                <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#E8541A"></span>
                        {{ count($this->getLeads()) }} {{ count($this->getLeads()) === 1 ? 'lead' : 'leads' }}
                    </span>
                </div>
            </div>

            <style>
                @import url('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
            </style>

            <div wire:ignore>
                <div
                    x-data="{
                        mapa: null,
                        marcadores: [],
                        leads: {{ json_encode($this->getLeads()) }},

                        init() {
                            if (typeof L === 'undefined') {
                                let script = document.createElement('script');
                                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                                document.head.appendChild(script);
                                script.onload = () => this.criarMapa();
                            } else {
                                this.criarMapa();
                            }

                            setInterval(() => {
                                this.$wire.refreshMapData().then((dados) => {
                                    this.leads = dados;
                                    this.plotarMarcadores(false);
                                });
                            }, 30000);
                        },

                        criarIcone(cor) {
                            return L.divIcon({
                                className: '',
                                html: '<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'26\' height=\'34\' viewBox=\'0 0 26 34\'>'
                                    + '<path d=\'M13 0C5.8 0 0 5.8 0 13c0 9.5 13 21 13 21s13-11.5 13-21C26 5.8 20.2 0 13 0z\' fill=\'' + cor + '\' stroke=\'white\' stroke-width=\'1.5\'/>'
                                    + '<circle cx=\'13\' cy=\'13\' r=\'5\' fill=\'white\'/>'
                                    + '</svg>',
                                iconSize: [26, 34],
                                iconAnchor: [13, 34],
                                popupAnchor: [0, -30],
                            });
                        },

                        criarMapa() {
                            this.mapa = L.map(this.$refs.mapaComercialCentral).setView([-15.7801, -47.9292], 4.5);

                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '© OpenStreetMap'
                            }).addTo(this.mapa);

                            this.plotarMarcadores(true);

                            setTimeout(() => this.mapa.invalidateSize(), 500);
                        },

                        plotarMarcadores(ajustarView) {
                            this.marcadores.forEach(m => this.mapa.removeLayer(m));
                            this.marcadores = [];

                            let bounds = [];
                            let iconeLead = this.criarIcone('#E8541A');

                            this.leads.forEach(lead => {
                                if (lead.latitude && lead.longitude) {
                                    let lat = parseFloat(lead.latitude);
                                    let lng = parseFloat(lead.longitude);

                                    let marcador = L.marker([lat, lng], { icon: iconeLead })
                                        .addTo(this.mapa)
                                        .bindPopup('<b>' + lead.company_name + '</b><br><span style=\'color:#6b7280\'>' + lead.stage_label + '</span><br><a href=\'' + lead.url + '\' style=\'color:#E8541A\'>Abrir lead</a>');
                                    this.marcadores.push(marcador);

                                    bounds.push([lat, lng]);
                                }
                            });

                            if (ajustarView && bounds.length > 0) {
                                this.mapa.fitBounds(bounds, { padding: [30, 30] });
                            }
                        }
                    }"
                >
                    <div x-ref="mapaComercialCentral" style="height: 500px; width: 100%; border-radius: 8px; z-index: 1;"></div>
                </div>
            </div>

            @if(count($this->getLeads()) === 0)
                <p class="text-xs text-gray-500 mt-3">Nenhum lead com endereço geolocalizado ainda. Preencha o CEP no cadastro do lead para ele aparecer aqui.</p>
            @endif
        </x-filament::section>
    </x-filament-widgets::widget>
</div>

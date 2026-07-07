<div>
    <x-filament-widgets::widget>
        <x-filament::section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Mapa de Leads</h2>
                <span class="text-xs text-gray-500">{{ count($this->getLeads()) }} com endereço geolocalizado</span>
            </div>

            <style>
                @import url('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
            </style>

            <div wire:ignore>
                <div
                    x-data="{
                        mapa: null,
                        leads: {{ json_encode($this->getLeads()) }},

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
                            this.mapa = L.map(this.$refs.mapaLeads).setView([-22.9056, -47.0608], 6);

                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '© OpenStreetMap'
                            }).addTo(this.mapa);

                            let bounds = [];

                            this.leads.forEach(lead => {
                                if (lead.latitude && lead.longitude) {
                                    let lat = parseFloat(lead.latitude);
                                    let lng = parseFloat(lead.longitude);
                                    let nome = lead.name ?? 'Lead sem nome';
                                    let empresa = lead.company_name ? ('<br>' + lead.company_name) : '';

                                    L.marker([lat, lng])
                                        .addTo(this.mapa)
                                        .bindPopup('<b>' + nome + '</b>' + empresa + '<br><span style=\'color:#6b7280\'>' + lead.stage_label + '</span><br><a href=\'' + lead.url + '\' style=\'color:#E8541A\'>Abrir lead</a>');

                                    bounds.push([lat, lng]);
                                }
                            });

                            if (bounds.length > 0) {
                                this.mapa.fitBounds(bounds, { padding: [30, 30] });
                            }

                            setTimeout(() => this.mapa.invalidateSize(), 500);
                        }
                    }"
                >
                    <div x-ref="mapaLeads" style="height: 600px; width: 100%; border-radius: 8px; z-index: 1;"></div>
                </div>
            </div>

            @if(count($this->getLeads()) === 0)
                <p class="text-xs text-gray-500 mt-3">Nenhum lead com endereço geolocalizado ainda. Preencha o endereço no cadastro do lead para ele aparecer aqui.</p>
            @endif
        </x-filament::section>
    </x-filament-widgets::widget>
</div>

<div>
    <x-filament-widgets::widget>
        <x-filament::section>
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Mapa Comercial</h2>
                <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#2563eb"></span>
                        {{ count($this->getClients()) }} {{ count($this->getClients()) === 1 ? 'cliente' : 'clientes' }}
                    </span>
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
                        clientes: {{ json_encode($this->getClients()) }},
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

                            // O mapa vive em wire:ignore (Leaflet precisa manter o DOM
                            // pra ele mesmo, Livewire nao pode remexer) -- entao um
                            // cliente/lead novo nunca aparecia sozinho sem recarregar a
                            // pagina inteira. Busca dado fresco periodicamente e so'
                            // redesenha os marcadores (o mapa em si nao e' recriado).
                            setInterval(() => {
                                this.$wire.refreshMapData().then((dados) => {
                                    this.clientes = dados.clientes;
                                    this.leads = dados.leads;
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
                            this.mapa = L.map(this.$refs.mapaComercial).setView([-22.9056, -47.0608], 6);

                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '© OpenStreetMap'
                            }).addTo(this.mapa);

                            this.plotarMarcadores(true);

                            setTimeout(() => this.mapa.invalidateSize(), 500);
                        },

                        // ajustarView=true so' no primeiro carregamento -- nos refreshes
                        // periodicos nao faz sentido recentralizar/dar zoom sozinho
                        // enquanto o usuario pode estar navegando o mapa.
                        plotarMarcadores(ajustarView) {
                            this.marcadores.forEach(m => this.mapa.removeLayer(m));
                            this.marcadores = [];

                            let bounds = [];
                            let iconeCliente = this.criarIcone('#2563eb');
                            let iconeLead = this.criarIcone('#E8541A');

                            this.clientes.forEach(cliente => {
                                if (cliente.latitude && cliente.longitude) {
                                    let lat = parseFloat(cliente.latitude);
                                    let lng = parseFloat(cliente.longitude);
                                    let nome = cliente.name ?? 'Cliente sem nome';
                                    let cidade = cliente.city ? (cliente.city + (cliente.uf ? '/' + cliente.uf : '')) : '';

                                    let marcador = L.marker([lat, lng], { icon: iconeCliente })
                                        .addTo(this.mapa)
                                        .bindPopup('<b>' + nome + '</b>' + (cidade ? '<br><span style=\'color:#6b7280\'>' + cidade + '</span>' : '') + '<br><a href=\'' + cliente.url + '\' style=\'color:#2563eb\'>Abrir cliente</a>');
                                    this.marcadores.push(marcador);

                                    bounds.push([lat, lng]);
                                }
                            });

                            this.leads.forEach(lead => {
                                if (lead.latitude && lead.longitude) {
                                    let lat = parseFloat(lead.latitude);
                                    let lng = parseFloat(lead.longitude);
                                    let nome = lead.name ?? 'Lead sem nome';
                                    let empresa = lead.company_name ? ('<br>' + lead.company_name) : '';

                                    let marcador = L.marker([lat, lng], { icon: iconeLead })
                                        .addTo(this.mapa)
                                        .bindPopup('<b>' + nome + '</b>' + empresa + '<br><span style=\'color:#6b7280\'>' + lead.stage_label + '</span><br><a href=\'' + lead.url + '\' style=\'color:#E8541A\'>Abrir lead</a>');
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
                    <div x-ref="mapaComercial" style="height: 600px; width: 100%; border-radius: 8px; z-index: 1;"></div>
                </div>
            </div>

            @if(count($this->getClients()) === 0 && count($this->getLeads()) === 0)
                <p class="text-xs text-gray-500 mt-3">Nenhum cliente ou lead com endereço geolocalizado ainda. Preencha o CEP no cadastro de cliente ou lead para eles aparecerem aqui.</p>
            @endif
        </x-filament::section>
    </x-filament-widgets::widget>
</div>

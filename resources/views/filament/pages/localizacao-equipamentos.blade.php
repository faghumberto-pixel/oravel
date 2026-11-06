<x-filament-panels::page>
    <div wire:poll.30s="atualizar" class="flex flex-col gap-4">
        @if ($dados['simulado'])
            <div class="rounded-xl border border-sky-500/40 bg-sky-500/10 px-4 py-2 text-sm text-sky-700 dark:text-sky-300">
                <b>Demonstração:</b> as posições do rastreador são simuladas. Nos clientes, esta tela usa o rastreador (Traccar) de cada equipamento e,
                quando não há aparelho, o endereço da obra do contrato ativo.
            </div>
        @endif
        @if ($dados['sem_localizacao'] > 0)
            <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm text-amber-700 dark:text-amber-300">
                {{ $dados['sem_localizacao'] }} equipamento(s) sem localização: falta endereço da obra no contrato ativo, local no cadastro ou rastreador.
            </div>
        @endif

        <div
            x-data="mapaEquipamentos(@js($dados))"
            x-init="iniciar()"
            class="grid grid-cols-1 gap-4 lg:grid-cols-[22rem_1fr]"
        >
            {{-- Lista e filtros --}}
            <div class="flex max-h-[42rem] flex-col rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-white/5">
                <div class="space-y-2 border-b border-gray-200 p-3 dark:border-white/10">
                    <input type="search" x-model.debounce.200ms="busca" placeholder="Buscar equipamento, cliente ou cidade"
                        class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" @click="status = ''" :class="status === '' ? 'ring-2 ring-primary-500' : ''"
                            class="rounded-full bg-gray-500/15 px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-200">
                            Todos (<span x-text="pontos.length"></span>)
                        </button>
                        <template x-for="s in resumoStatus()" :key="s.chave">
                            <button type="button" @click="status = (status === s.chave ? '' : s.chave)" :class="status === s.chave ? 'ring-2 ring-primary-500' : ''"
                                class="inline-flex items-center gap-1 rounded-full bg-gray-500/15 px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-200">
                                <span class="h-2 w-2 rounded-full" :style="'background:' + s.cor"></span>
                                <span x-text="s.rotulo + ' (' + s.total + ')'"></span>
                            </button>
                        </template>
                        <button type="button" @click="soRastreador = !soRastreador" :class="soRastreador ? 'ring-2 ring-primary-500' : ''"
                            class="inline-flex items-center gap-1 rounded-full bg-sky-500/15 px-2.5 py-1 text-xs font-medium text-sky-700 dark:text-sky-300">
                            📡 Com rastreador (<span x-text="pontos.filter(p => p.rastreador).length"></span>)
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto">
                    <template x-for="p in filtrados()" :key="p.id">
                        <button type="button" @click="focar(p)" class="block w-full border-b border-gray-100 px-3 py-2 text-left hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="'background:' + corStatus(p.status)"></span>
                                <span class="truncate text-sm font-semibold text-gray-950 dark:text-white" x-text="p.nome"></span>
                                <span x-show="p.rastreador" class="ms-auto shrink-0 text-xs" title="Com rastreador">📡</span>
                            </div>
                            <div class="ms-4 truncate text-xs text-gray-500 dark:text-gray-400" x-text="(p.patrimonio || '') + ' · ' + p.status_rotulo + (p.cliente ? ' · ' + p.cliente : '')"></div>
                            <div class="ms-4 truncate text-xs text-gray-500 dark:text-gray-400" x-text="p.local"></div>
                        </button>
                    </template>
                    <div x-show="filtrados().length === 0" class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">Nenhum equipamento com esses filtros.</div>
                </div>
            </div>

            {{-- Mapa --}}
            <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10" wire:ignore>
                <div x-ref="mapa" class="h-[42rem] w-full" style="z-index: 1"></div>
                <div class="pointer-events-none absolute bottom-2 left-2 z-[500] rounded bg-white/90 px-2 py-1 text-[11px] text-gray-700 shadow">
                    Atualizado às <span x-text="atualizadoEm"></span> · a cada 30 s
                </div>
            </div>
        </div>
    </div>

    <style>
        .eq-pino { width: 26px; height: 26px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 2px solid #fff; box-shadow: 0 1px 4px rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; }
        .eq-pino span { transform: rotate(45deg); font-size: 12px; color: #fff; font-weight: 700; }
        .eq-pulso { position: absolute; inset: -6px; border-radius: 50%; border: 2px solid #0ea5e9; animation: eq-pulso 1.8s ease-out infinite; }
        @keyframes eq-pulso { 0% { transform: scale(.6); opacity: .9; } 100% { transform: scale(1.6); opacity: 0; } }
        .leaflet-popup-content { font-family: inherit; font-size: 13px; }
    </style>

    <script>
        window.mapaEquipamentos = function (inicial) {
            const CORES = { locado: '#2563eb', disponivel: '#16a34a', manutencao: '#dc2626', operando: '#0891b2', reservado: '#db2777', quarentena: '#9333ea', aguardando_triagem: '#f97316' };
            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const brl = (v) => 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            return {
                map: null, marcadores: {}, trilhas: {}, primeiraVez: true,
                pontos: inicial.pontos, atualizadoEm: inicial.atualizado_em,
                busca: '', status: '', soRastreador: false,

                corStatus(s) { return CORES[s] || '#6b7280'; },

                resumoStatus() {
                    const mapa = {};
                    this.pontos.forEach((p) => { mapa[p.status] = mapa[p.status] || { chave: p.status, rotulo: p.status_rotulo, cor: this.corStatus(p.status), total: 0 }; mapa[p.status].total++; });
                    return Object.values(mapa);
                },

                filtrados() {
                    const q = this.busca.trim().toLowerCase();
                    return this.pontos.filter((p) =>
                        (this.status === '' || p.status === this.status)
                        && (!this.soRastreador || p.rastreador)
                        && (q === '' || [p.nome, p.patrimonio, p.cliente, p.local, p.categoria].some((v) => (v || '').toLowerCase().includes(q))));
                },

                iniciar() {
                    this.$wire.on('pontos-atualizados', (e) => {
                        this.pontos = e.dados.pontos;
                        this.atualizadoEm = e.dados.atualizado_em;
                        this.desenhar();
                    });
                    this.$watch('busca', () => this.desenhar(true));
                    this.$watch('status', () => this.desenhar(true));
                    this.$watch('soRastreador', () => this.desenhar(true));
                    this.carregarLeaflet(() => this.criarMapa());
                },

                carregarLeaflet(cb) {
                    if (window.L) { cb(); return; }
                    const css = document.createElement('link');
                    css.rel = 'stylesheet'; css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                    document.head.appendChild(css);
                    const js = document.createElement('script');
                    js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                    js.onload = cb;
                    document.head.appendChild(js);
                },

                criarMapa() {
                    this.map = L.map(this.$refs.mapa, { zoomControl: true }).setView([-22.9056, -47.0608], 8);
                    // Camadas sem chave de API. Ruas (Esri) lembra o Google Maps; Satelite e Escuro tambem sao Esri; OSM e' o
                    // mapa aberto que o resto do app ja usa. Em producao em escala, trocar por um provedor com chave (MapTiler, Stadia, Google).
                    const esri = (caminho, atrib) => L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/' + caminho + '/MapServer/tile/{z}/{y}/{x}', { attribution: atrib, maxZoom: 19 });
                    const ruas = esri('World_Street_Map', 'Mapa © Esri, HERE, Garmin, OpenStreetMap');
                    const satelite = esri('World_Imagery', 'Imagens © Esri, Maxar, Earthstar Geographics');
                    const escuro = esri('Canvas/World_Dark_Gray_Base', 'Mapa © Esri, HERE, Garmin');
                    const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 19 });
                    ruas.addTo(this.map);
                    L.control.layers({ 'Ruas': ruas, 'Satélite': satelite, 'Escuro': escuro, 'OpenStreetMap': osm }, {}, { position: 'topright' }).addTo(this.map);
                    this.desenhar(true);
                    setTimeout(() => this.map.invalidateSize(), 400);
                },

                icone(p) {
                    const cor = this.corStatus(p.status);
                    const pulso = p.rastreador ? '<div class="eq-pulso"><\/div>' : '';
                    return L.divIcon({ className: '', iconSize: [26, 26], iconAnchor: [13, 26], popupAnchor: [0, -26],
                        html: '<div style="position:relative">' + pulso + '<div class="eq-pino" style="background:' + cor + '"><span>' + (p.rastreador ? '◉' : '•') + '<\/span><\/div><\/div>' });
                },

                popup(p) {
                    const sit = p.situacao ? '<div style="margin-top:4px"><b style="color:' + ({ red: '#dc2626', amber: '#d97706', emerald: '#059669', gray: '#6b7280' }[p.situacao.cor] || '#6b7280') + '">' + esc(p.situacao.rotulo) + '<\/b>' + (p.razao !== null ? ' · ' + p.razao + '% do valor' : '') + '<\/div>' : '';
                    return '<div style="min-width:220px">'
                        + '<div style="font-weight:700;font-size:14px">' + esc(p.nome) + '<\/div>'
                        + '<div style="color:#6b7280">' + esc(p.patrimonio || '') + ' · ' + esc(p.categoria || '') + '<\/div>'
                        + '<div style="margin-top:4px"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' + this.corStatus(p.status) + '"><\/span> ' + esc(p.status_rotulo) + '<\/div>'
                        + (p.cliente ? '<div style="margin-top:4px"><b>Cliente:<\/b> ' + esc(p.cliente) + '<\/div>' : '')
                        + '<div><b>Local:<\/b> ' + esc(p.local) + '<\/div>'
                        + (p.contrato ? '<div><b>Contrato:<\/b> ' + esc(p.contrato.numero) + (p.contrato.ate ? ' (até ' + esc(p.contrato.ate) + ')' : '') + '<\/div>' : '')
                        + '<div style="margin-top:4px"><b>Manutenção acumulada:<\/b> ' + brl(p.custo_total) + '<\/div>' + sit
                        + '<div style="margin-top:4px;color:#6b7280;font-size:11px">Fonte da posição: ' + esc(p.fonte_rotulo) + '<\/div>'
                        + (p.rastreador ? '<div style="font-size:11px;color:#0369a1">📡 ' + esc(p.velocidade) + ' km/h · atualizado ' + esc(p.atualizado_em || '') + '<\/div>' : '')
                        + '<div style="margin-top:6px"><a href="' + esc(p.url) + '" style="color:#2563eb;font-weight:600">Abrir cadastro e custos →<\/a><\/div><\/div>';
                },

                desenhar(reenquadrar = false) {
                    if (!this.map) return;
                    const visiveis = this.filtrados();
                    const ids = new Set(visiveis.map((p) => p.id));

                    Object.keys(this.marcadores).forEach((id) => {
                        if (!ids.has(id)) { this.map.removeLayer(this.marcadores[id]); delete this.marcadores[id]; }
                    });
                    Object.keys(this.trilhas).forEach((id) => {
                        if (!ids.has(id)) { this.map.removeLayer(this.trilhas[id]); delete this.trilhas[id]; }
                    });

                    visiveis.forEach((p) => {
                        if (this.marcadores[p.id]) {
                            this.marcadores[p.id].setLatLng([p.lat, p.lng]).setIcon(this.icone(p));
                            this.marcadores[p.id].getPopup().setContent(this.popup(p));
                        } else {
                            this.marcadores[p.id] = L.marker([p.lat, p.lng], { icon: this.icone(p) }).addTo(this.map).bindPopup(this.popup(p));
                        }
                        if (p.trilha && p.trilha.length > 1) {
                            if (this.trilhas[p.id]) { this.trilhas[p.id].setLatLngs(p.trilha); }
                            else { this.trilhas[p.id] = L.polyline(p.trilha, { color: '#0ea5e9', weight: 3, opacity: 0.85, dashArray: '6 6' }).addTo(this.map); }
                        }
                    });

                    if ((reenquadrar || this.primeiraVez) && visiveis.length) {
                        this.map.fitBounds(L.latLngBounds(visiveis.map((p) => [p.lat, p.lng])), { padding: [40, 40], maxZoom: 13 });
                        this.primeiraVez = false;
                    }
                },

                focar(p) {
                    if (!this.map || !this.marcadores[p.id]) return;
                    this.map.flyTo([p.lat, p.lng], Math.max(this.map.getZoom(), 13), { duration: 0.8 });
                    this.marcadores[p.id].openPopup();
                },
            };
        };
    </script>
</x-filament-panels::page>

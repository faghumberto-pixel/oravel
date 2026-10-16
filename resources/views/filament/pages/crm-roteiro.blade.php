<x-filament-panels::page>
    @php
        $visitas = $this->visitas;
        $btn = 'px-3 py-2 text-xs font-bold rounded-lg border transition-colors';
        $campo = 'text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800';
    @endphp

    <div class="flex flex-wrap items-center gap-3">
        <input type="date" wire:model.live="dia" class="{{ $campo }}" />
        @if($this->podeEscolherVendedor())
            <select wire:model.live="vendedorId" class="{{ $campo }}">
                @foreach($this->vendedores as $id => $nome)<option value="{{ $id }}">{{ $nome }}</option>@endforeach
            </select>
        @endif
        <button type="button" wire:click="otimizar" class="{{ $btn }} bg-primary-600 text-white border-primary-600">Otimizar ordem</button>
        <button type="button"
            onclick="navigator.geolocation ? navigator.geolocation.getCurrentPosition(p => @this.definirOrigem(p.coords.latitude, p.coords.longitude), () => @this.otimizar()) : @this.otimizar()"
            class="{{ $btn }} border-gray-300 dark:border-gray-600">Otimizar a partir da minha posição</button>
        @if($this->linkRota)
            <a href="{{ $this->linkRota }}" target="_blank" rel="noopener" class="{{ $btn }} border-gray-300 dark:border-gray-600">Abrir rota no Google Maps</a>
        @endif
        <span class="text-sm text-gray-600 dark:text-gray-300">≈ {{ number_format($this->kmEstimados, 1, ',', '.') }} km (estimativa)</span>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-filament::section heading="Visitas do dia">
            <ol class="space-y-2">
                @forelse($visitas as $i => $v)
                    <li class="flex items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-700 p-3" wire:key="rot-{{ $v->id }}">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white">{{ $i + 1 }}</span>
                        <div class="flex-1">
                            <p class="text-sm font-semibold">{{ $v->alvoNome() }}</p>
                            <p class="text-xs text-gray-500">{{ $v->agendada_para?->format('H:i') }} · {{ $v->endereco ?: 'sem endereço' }} {{ $v->cidade }}
                                @unless($v->temCoordenadas()) · <span class="text-amber-600">sem localização no mapa</span> @endunless</p>
                        </div>
                        <button type="button" wire:click="mover('{{ $v->id }}', 'subir')" class="text-xs underline" @disabled($i === 0)>subir</button>
                        <button type="button" wire:click="mover('{{ $v->id }}', 'descer')" class="text-xs underline" @disabled($i === $visitas->count() - 1)>descer</button>
                    </li>
                @empty
                    <li class="text-sm text-gray-500">Nenhuma visita aberta neste dia.</li>
                @endforelse
            </ol>
        </x-filament::section>

        <x-filament::section heading="Mapa">
            <div wire:ignore
                 x-data="{
                    pontos: @js($this->pontosDoMapa()), mapa: null,
                    init() {
                        if (window.L) { this.criar(); return; }
                        let css = document.createElement('link'); css.rel = 'stylesheet'; css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'; document.head.appendChild(css);
                        let s = document.createElement('script'); s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'; s.onload = () => this.criar(); document.head.appendChild(s);
                    },
                    criar() {
                        this.mapa = L.map(this.$refs.mapa).setView([-15.78, -47.93], 4);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(this.mapa);
                        this.desenhar();
                    },
                    desenhar() {
                        if (!this.mapa) return;
                        this.mapa.eachLayer(l => { if (l instanceof L.Marker || l instanceof L.Polyline) this.mapa.removeLayer(l); });
                        let ll = [];
                        this.pontos.forEach(p => {
                            ll.push([p.lat, p.lng]);
                            L.marker([p.lat, p.lng], { icon: L.divIcon({ className: '', html: '<div style=\'background:#E8541A;color:#fff;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-weight:700\'>' + p.n + '</div>' }) }).addTo(this.mapa).bindPopup(p.nome);
                        });
                        if (ll.length > 1) L.polyline(ll, { color: '#E8541A', weight: 3 }).addTo(this.mapa);
                        if (ll.length) this.mapa.fitBounds(ll, { padding: [30, 30], maxZoom: 15 });
                    }
                 }"
                 x-init="init()"
                 @roteiro-atualizado.window="pontos = $event.detail.pontos; desenhar()">
                <div x-ref="mapa" style="height: 480px; width: 100%; border-radius: 8px; z-index: 1;"></div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>

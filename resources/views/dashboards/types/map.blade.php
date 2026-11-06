@php $mapaId = 'odbmap-'.\Illuminate\Support\Str::random(8); @endphp
@if (count($p['points']) === 0)
    <p class="odb-empty">Nenhum equipamento com localização.</p>
@else
    <div class="odb-map-w" wire:ignore>
        <div id="{{ $mapaId }}" class="odb-map" x-data x-init="window.odbMapa($el, @js($p['points']))"></div>
    </div>
    <div class="odb-legend">
        @foreach ($p['legend'] as $l)<span><b style="background: {{ $l[1] }}"></b>{{ $l[0] }}</span>@endforeach
        @if ($p['link'])<a class="odb-link" href="{{ $p['link'] }}">{{ $p['linkLabel'] ?: 'Abrir mapa completo' }} →</a>@endif
    </div>
    @once
        <script>
            // Espera o CSS e o JS do Leaflet carregarem antes de criar qualquer mapa (senao os ladrilhos saem sem estilo).
            window.odbLeaflet = function (cb) {
                if (window.L && window.__odbLeafletCss) { cb(); return; }
                (window.__odbLeafletCbs = window.__odbLeafletCbs || []).push(cb);
                if (window.__odbLeafletLoading) { return; }
                window.__odbLeafletLoading = true;
                let pendentes = window.L ? 1 : 2;
                const pronto = () => {
                    if (--pendentes > 0) { return; }
                    window.__odbLeafletCss = true;
                    (window.__odbLeafletCbs || []).forEach((f) => f());
                    window.__odbLeafletCbs = [];
                };
                const css = document.createElement('link');
                css.rel = 'stylesheet'; css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                css.onload = pronto; css.onerror = pronto;
                document.head.appendChild(css);
                if (window.L) { return; }
                const js = document.createElement('script');
                js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                js.onload = pronto; js.onerror = pronto;
                document.head.appendChild(js);
            };
            window.odbMapa = function (el, pontos) {
                window.odbLeaflet(() => {
                    if (el._odbMapa) { return; }
                    const mapa = L.map(el, { zoomControl: true, scrollWheelZoom: false });
                    el._odbMapa = mapa;
                    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', { attribution: 'Mapa © Esri, HERE, Garmin, OpenStreetMap', maxZoom: 19 }).addTo(mapa);
                    const limites = [];
                    pontos.forEach((p) => {
                        limites.push([p[0], p[1]]);
                        const caixa = document.createElement('div');
                        const titulo = document.createElement('strong');
                        titulo.textContent = p[3];
                        caixa.appendChild(titulo);
                        caixa.appendChild(document.createElement('br'));
                        caixa.appendChild(document.createTextNode(p[4] || ''));
                        L.circleMarker([p[0], p[1]], { radius: 8, color: '#ffffff', weight: 2, fillColor: p[2], fillOpacity: 1 }).addTo(mapa).bindPopup(caixa);
                    });
                    mapa.fitBounds(L.latLngBounds(limites), { padding: [28, 28], maxZoom: 12 });
                    setTimeout(() => mapa.invalidateSize(), 300);
                });
            };
        </script>
    @endonce
@endif

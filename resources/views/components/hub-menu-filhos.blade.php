{{--
    Conteúdo das páginas de menu pai (Gestão de Ativos, Gestão CRM, Almoxarifado, ...): um cartão com ícone para cada
    menu filho. Os filhos vêm do próprio menu lateral (já filtrado por contrato, permissão e canAccess), então um cliente
    só vê o que realmente pode abrir.
--}}
@php
    $caminhoAtual = rtrim((string) parse_url(url()->current(), PHP_URL_PATH), '/');
    $filhos = [];
    $nomeDoGrupo = null;

    foreach (\Filament\Facades\Filament::getNavigation() as $grupo) {
        foreach ($grupo->getItems() as $item) {
            if (rtrim((string) parse_url((string) $item->getUrl(), PHP_URL_PATH), '/') === $caminhoAtual) {
                $filhos = $item->getChildItems();
                $nomeDoGrupo = $grupo->getLabel();
                break 2;
            }
        }
    }

    $cores = ['#ea580c', '#2563eb', '#059669', '#7c3aed', '#db2777', '#0284c7', '#ca8a04', '#dc2626'];
@endphp

<style>
    /* Cartões sóbrios: ícone em bloco suave, borda fina e destaque discreto ao passar o mouse. */
    .hub-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem; }
    .hub-card { --hub-cor: #ea580c; display: flex; flex-direction: column; border-radius: .75rem; background: #fff; border: 1px solid #e2e8f0; text-decoration: none; color: inherit; transition: border-color .2s, box-shadow .2s, transform .2s; }
    .hub-card:hover, .hub-card:focus-visible { border-color: var(--hub-cor); box-shadow: 0 6px 18px rgba(15,23,42,.10); transform: translateY(-2px); outline: none; }
    .hub-top { display: flex; align-items: center; gap: .9rem; padding: 1.15rem 1.15rem 1rem; }
    .hub-ico { flex: none; width: 2.75rem; height: 2.75rem; border-radius: .7rem; display: grid; place-items: center; color: var(--hub-cor); background: color-mix(in srgb, var(--hub-cor) 12%, transparent); }
    .hub-ico svg { width: 1.45rem; height: 1.45rem; }
    .hub-title { font-weight: 600; font-size: .92rem; line-height: 1.3; color: #0f172a; }
    .hub-sub { margin-top: .15rem; font-size: .72rem; letter-spacing: .02em; color: #64748b; }
    .hub-foot { display: flex; align-items: center; justify-content: space-between; margin-top: auto; padding: .6rem 1.15rem; border-top: 1px solid #f1f5f9; font-size: .72rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #64748b; transition: color .2s; }
    .hub-card:hover .hub-foot { color: var(--hub-cor); }
    .hub-foot svg { width: .95rem; height: .95rem; }
    .dark .hub-card { background: #1c2230; border-color: #2d3548; }
    .dark .hub-title { color: #f1f5f9; } .dark .hub-sub { color: #94a3b8; } .dark .hub-foot { border-color: #2d3548; color: #94a3b8; }
    .hub-vazio { color: #64748b; }
    @media (prefers-reduced-motion: reduce) { .hub-card { transition: none; } .hub-card:hover { transform: none; } }
</style>

@if (count($filhos))
    <div class="hub-grid">
        @foreach ($filhos as $i => $filho)
            @php
                $icone = $filho->getIcon();
                $icone = is_string($icone) && $icone !== '' ? $icone : 'heroicon-o-squares-2x2';
            @endphp
            <a href="{{ $filho->getUrl() }}" class="hub-card" style="--hub-cor: {{ $cores[$i % count($cores)] }}" @if ($filho->shouldOpenUrlInNewTab()) target="_blank" rel="noopener" @endif>
                <div class="hub-top">
                    <span class="hub-ico">
                        <x-filament::icon :icon="$icone" />
                    </span>
                    <div>
                        <div class="hub-title">{{ $filho->getLabel() }}</div>
                    </div>
                </div>
                <div class="hub-foot"><span>Abrir</span><x-filament::icon icon="heroicon-m-arrow-right" /></div>
            </a>
        @endforeach
    </div>
@else
    <x-filament::section>
        <p class="hub-vazio">Nenhuma opção deste menu está liberada no seu plano ou perfil de acesso. Fale com o administrador da empresa.</p>
    </x-filament::section>
@endif

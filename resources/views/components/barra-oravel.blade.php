{{-- Barra fina no topo das telas de celular: as 3 bolinhas (vermelha, amarela, verde) da barra de janela do site + "Oravel" em branco. --}}
<div {{ $attributes->class(['flex h-8 shrink-0 items-center gap-1.5 px-4']) }} style="background:#0f172a" data-testid="barra-oravel">
    <span class="block h-2.5 w-2.5 rounded-full" style="background:#ef4444"></span>
    <span class="block h-2.5 w-2.5 rounded-full" style="background:#f59e0b"></span>
    <span class="block h-2.5 w-2.5 rounded-full" style="background:#22c55e"></span>
    <span class="ms-2 text-sm font-bold tracking-tight text-white">Oravel</span>
</div>

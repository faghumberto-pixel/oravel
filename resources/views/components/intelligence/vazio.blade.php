@props(['titulo', 'texto', 'itens' => [], 'cor' => '#4f46e5', 'url'])
<x-filament::section>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:20px;align-items:center;padding:8px 4px">
        <div>
            <div style="display:inline-block;font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:{{ $cor }};background:{{ $cor }}1a;border-radius:999px;padding:3px 12px">Oravel Intelligence</div>
            <h2 style="font-size:1.35rem;font-weight:800;margin:10px 0 6px">{{ $titulo }}</h2>
            <p style="opacity:.75;line-height:1.55;margin:0 0 16px">{{ $texto }}</p>
            <x-filament::button tag="a" :href="$url">Importar a primeira planilha</x-filament::button>
        </div>
        <div style="display:grid;gap:10px">
            @foreach ($itens as $it)
                <div style="display:flex;gap:12px;align-items:flex-start;border-radius:12px;padding:12px 14px;border:1px solid {{ $cor }}33;background:{{ $cor }}0d">
                    <span style="flex:none;width:28px;height:28px;border-radius:50%;background:{{ $cor }};color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center">{{ $loop->iteration }}</span>
                    <div><b style="font-size:.9rem">{{ $it[0] }}</b><div style="font-size:.8rem;opacity:.7;line-height:1.45">{{ $it[1] }}</div></div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament::section>

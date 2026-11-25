<div x-data x-on:abrir-tour.window="$wire.abrir()">
    @if ($passo)
        <style>
            .tg-fundo{position:fixed;inset:0;z-index:9999;background:rgba(2,6,23,.62);display:flex;align-items:center;justify-content:center;padding:16px}
            .tg-card{position:relative;width:100%;max-width:560px;max-height:calc(100dvh - 32px);overflow:auto;background:#fff;color:#1f2937;border-radius:16px;padding:26px 24px 20px;box-shadow:0 30px 70px -20px rgba(0,0,0,.55)}
            .tg-x{position:absolute;right:8px;top:8px;border:0;background:transparent;font-size:26px;line-height:1;cursor:pointer;min-width:44px;min-height:44px;color:#6b7280}
            .tg-eyebrow{margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:rgb(var(--primary-600,234 88 12))}
            .tg-card h2{margin:0 0 4px;font-size:21px;line-height:1.25;font-weight:800;padding-right:28px}
            .tg-resumo{margin:0 0 14px;color:#4b5563;font-size:15px}
            .tg-blocos{margin:0;display:grid;gap:10px}
            .tg-blocos div{border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px}
            .tg-blocos dt{font-size:12px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:rgb(var(--primary-600,234 88 12));margin:0 0 3px}
            .tg-blocos dd{margin:0;font-size:14.5px;line-height:1.5}
            .tg-nota{margin:12px 0 0;padding:9px 12px;border-radius:10px;background:rgb(var(--primary-50,255 247 237));font-size:13.5px}
            .tg-pontos{display:flex;gap:6px;justify-content:center;margin:16px 0 4px}
            .tg-pontos i{width:8px;height:8px;border-radius:50%;background:#d1d5db}.tg-pontos i.on{background:rgb(var(--primary-600,234 88 12));width:22px;border-radius:99px}
            .tg-acoes{display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end;margin-top:12px}
            .tg-btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 16px;border-radius:10px;font-weight:700;font-size:14px;text-decoration:none;cursor:pointer;border:1.5px solid #d1d5db;background:#fff;color:#1f2937}
            .tg-btn.pri{background:rgb(var(--primary-600,234 88 12));border-color:rgb(var(--primary-600,234 88 12));color:#fff}
            .tg-link{display:block;margin:10px auto 0;border:0;background:transparent;color:#6b7280;font-size:12.5px;text-decoration:underline;cursor:pointer}
            .tg-card :focus-visible{outline:3px solid rgb(var(--primary-500,249 115 22));outline-offset:2px}
            .dark .tg-card{background:rgb(var(--gray-900,17 24 39));color:#e5e7eb}
            .dark .tg-resumo{color:#cbd5e1}.dark .tg-blocos div{border-color:rgba(255,255,255,.14)}
            .dark .tg-btn{background:transparent;border-color:rgba(255,255,255,.28);color:#e5e7eb}.dark .tg-btn.pri{background:rgb(var(--primary-600,234 88 12));color:#fff}
            .dark .tg-nota{background:rgba(255,255,255,.07)}.dark .tg-pontos i{background:rgba(255,255,255,.25)}.dark .tg-pontos i.on{background:rgb(var(--primary-500,249 115 22))}
            .dark .tg-x,.dark .tg-link{color:#9ca3af}
            @media (prefers-reduced-motion:no-preference){.tg-card{animation:tgin .18s ease-out}@keyframes tgin{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}}
        </style>
        <div class="tg-fundo" x-data x-trap.noscroll="true" x-on:keydown.escape.window="$wire.fechar()" role="dialog" aria-modal="true" aria-labelledby="tg-titulo" data-tour-guiado>
            <div class="tg-card">
                <button type="button" class="tg-x" wire:click="fechar" aria-label="Fechar">×</button>
                <p class="tg-eyebrow">O que é esta tela · {{ $posicao }} de {{ $total }}</p>
                <h2 id="tg-titulo">{{ $passo['titulo'] }}</h2>
                <p class="tg-resumo">{{ $passo['resumo'] }}</p>
                <dl class="tg-blocos">
                    <div><dt>Por que usar</dt><dd>{{ $passo['porque'] }}</dd></div>
                    <div><dt>Quando usar</dt><dd>{{ $passo['quando'] }}</dd></div>
                    <div><dt>Como fazer</dt><dd>{{ $passo['como'] }}</dd></div>
                </dl>
                @if ($nota)<p class="tg-nota">{{ $nota }}</p>@endif
                <div class="tg-pontos" aria-hidden="true">@for ($n = 1; $n <= $total; $n++)<i @class(['on' => $n === $posicao])></i>@endfor</div>
                <div class="tg-acoes">
                    @if ($anterior)<a class="tg-btn" href="{{ $anterior['url'] }}?tour=abrir">← Tela anterior</a>@endif
                    <button type="button" class="tg-btn" wire:click="fechar">Entendi</button>
                    @if ($proximo)
                        <a class="tg-btn pri" href="{{ $proximo['url'] }}?tour=abrir">Próxima tela →</a>
                    @else
                        <button type="button" class="tg-btn pri" wire:click="fechar">Concluir</button>
                    @endif
                </div>
                <button type="button" class="tg-link" wire:click="naoMostrarMais">Não abrir sozinho de novo</button>
            </div>
        </div>
    @endif
</div>

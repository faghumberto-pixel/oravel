@php
    $e = $this->getEstado();
@endphp
<x-filament-panels::page>
    <style>
        .pp { max-width: 760px; margin: 0 auto; display: flex; flex-direction: column; gap: 1rem; }
        .pp-h1 { font-size: 1.6rem; font-weight: 700; line-height: 1.2; color: #0f172a; margin: 0; }
        .pp-sub { margin-top: .35rem; font-size: .95rem; color: #64748b; }
        .pp-prog { display: flex; align-items: center; gap: .9rem; }
        .pp-bar { flex: 1; height: .5rem; border-radius: 9999px; background: #e2e8f0; overflow: hidden; }
        .pp-bar > span { display: block; height: 100%; border-radius: 9999px; background: #ea580c; transition: width .4s; }
        .pp-pass { font-size: .8rem; font-weight: 600; color: #64748b; white-space: nowrap; }
        .pp-card { border: 1px solid #e2e8f0; background: #fff; border-radius: .9rem; padding: 1.1rem 1.25rem; }
        .pp-card.atual { border-color: #ea580c; box-shadow: 0 0 0 3px rgba(234,88,12,.12); }
        .pp-card.feito { background: #f8fafc; }
        .pp-top { display: flex; align-items: flex-start; gap: .85rem; }
        .pp-num { flex: none; width: 2rem; height: 2rem; border-radius: 9999px; display: grid; place-items: center; font-size: .85rem; font-weight: 700; background: #f1f5f9; color: #475569; }
        .pp-card.atual .pp-num { background: #ea580c; color: #fff; }
        .pp-card.feito .pp-num { background: #16a34a; color: #fff; }
        .pp-num svg { width: 1.1rem; height: 1.1rem; }
        .pp-t { flex: 1; min-width: 0; }
        .pp-t b { display: block; font-size: 1rem; color: #0f172a; }
        .pp-t span { display: block; margin-top: .2rem; font-size: .88rem; line-height: 1.5; color: #64748b; }
        .pp-min { font-size: .75rem; color: #94a3b8; white-space: nowrap; }
        .pp-acoes { display: flex; flex-wrap: wrap; gap: .6rem; margin: .95rem 0 0 2.85rem; }
        .pp-ajuda { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; border-radius: .9rem; background: #f1f5f9; padding: .9rem 1.25rem; font-size: .9rem; color: #475569; }
        .pp-fim { border: 1px solid #bbf7d0; background: #f0fdf4; border-radius: .9rem; padding: 1.25rem; color: #166534; }
        .pp-fim b { display: block; font-size: 1.05rem; margin-bottom: .3rem; }
        .dark .pp-h1, .dark .pp-t b { color: #f8fafc; } .dark .pp-sub, .dark .pp-t span, .dark .pp-pass { color: #94a3b8; }
        .dark .pp-card { background: #1c2230; border-color: #2d3548; } .dark .pp-card.feito { background: #171c28; } .dark .pp-bar { background: #2d3548; }
        .dark .pp-num { background: #2d3548; color: #cbd5e1; } .dark .pp-ajuda { background: #171c28; color: #94a3b8; } .dark .pp-fim { background: #0f2a1a; border-color: #14532d; color: #86efac; }
        @media (max-width: 640px) { .pp-acoes { margin-left: 0; } .pp-top { flex-wrap: wrap; } }
    </style>

    <div class="pp">
        <div>
            <h1 class="pp-h1">Bem-vindo, {{ $this->nomeDoUsuario() }}</h1>
            @if ($e['completo'])
                <p class="pp-sub">Você concluiu os primeiros passos da solução {{ $e['titulo'] }}.</p>
            @else
                <p class="pp-sub">Faltam {{ $e['total'] - $e['concluidos'] }} {{ $e['total'] - $e['concluidos'] === 1 ? 'passo' : 'passos' }} para {{ $e['meta'] }}. Leva cerca de {{ $e['minutos_restantes'] }} minutos.</p>
            @endif
        </div>

        <div class="pp-prog" role="progressbar" aria-valuenow="{{ $e['percentual'] }}" aria-valuemin="0" aria-valuemax="100">
            <div class="pp-bar"><span style="width: {{ $e['percentual'] }}%"></span></div>
            <span class="pp-pass">{{ $e['completo'] ? 'Concluído' : 'Passo '.($e['atual'] + 1).' de '.$e['total'] }}</span>
        </div>

        @foreach ($e['passos'] as $i => $passo)
            <div class="pp-card {{ $passo['concluido'] ? 'feito' : ($e['atual'] === $i ? 'atual' : '') }}">
                <div class="pp-top">
                    <span class="pp-num">
                        @if ($passo['concluido'])<x-filament::icon icon="heroicon-m-check" />@else{{ $passo['numero'] }}@endif
                    </span>
                    <div class="pp-t"><b>{{ $passo['titulo'] }}</b><span>{{ $passo['descricao'] }}</span></div>
                    <span class="pp-min">{{ $passo['concluido'] ? 'Feito' : $passo['minutos'].' min' }}</span>
                </div>
                @if (! $passo['concluido'])
                    <div class="pp-acoes">
                        @foreach ($passo['acoes'] as $acao)
                            @php $url = $this->urlDaAcao($acao); @endphp
                            @if (($acao['acao'] ?? null) === 'baixar_modelo')
                                <x-filament::button color="gray" size="sm" :icon="$acao['icone'] ?? null" wire:click="mountAction('baixarModelo')">{{ $acao['rotulo'] }}</x-filament::button>
                            @elseif (($acao['acao'] ?? null) === 'importar')
                                <x-filament::button color="gray" size="sm" :icon="$acao['icone'] ?? null" wire:click="mountAction('importar')">{{ $acao['rotulo'] }}</x-filament::button>
                            @elseif ($url)
                                <x-filament::button color="gray" size="sm" tag="a" :href="$url" :icon="$acao['icone'] ?? null">{{ $acao['rotulo'] }}</x-filament::button>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        @if ($e['completo'])
            <div class="pp-fim">
                <b>Tudo certo por aqui</b>
                {{ $e['conclusao'] }} Quando quiser ampliar, veja as outras soluções da Oravel.
                <div style="margin-top:.8rem; display:flex; gap:.6rem; flex-wrap:wrap">
                    <x-filament::button tag="a" href="https://oravel.com.br/planos/" target="_blank" size="sm">Ver outras soluções</x-filament::button>
                    <x-filament::button color="gray" size="sm" wire:click="dispensar">Fechar este guia</x-filament::button>
                </div>
            </div>
        @else
            <div class="pp-ajuda">
                <span>Travou em algum passo? Fale com a gente pelo WhatsApp.</span>
                <span style="display:flex; gap:.5rem; flex-wrap:wrap">
                    <x-filament::button color="gray" size="sm" tag="a" :href="$this->linkDeAjuda($e)" target="_blank" icon="heroicon-o-chat-bubble-left-right">Pedir ajuda</x-filament::button>
                    <x-filament::button color="gray" size="sm" outlined wire:click="dispensar" wire:confirm="Fechar o guia por enquanto? Você pode voltar a ele pelo menu.">Agora não</x-filament::button>
                </span>
            </div>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>

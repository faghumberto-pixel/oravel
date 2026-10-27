<x-filament-panels::page>
    @include('dashboards.styles')

    <div class="odb">
        @if ($paineis->count() > 1)
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
                @foreach ($paineis as $painel)
                    <button type="button" wire:click="selecionarPainel('{{ $painel->id }}')"
                        style="font:600 13px sans-serif;border-radius:8px;padding:7px 12px;cursor:pointer;border:1px solid var(--odb-border);background:{{ $painel->id === $atual?->id ? 'var(--odb-accent)' : 'transparent' }};color:{{ $painel->id === $atual?->id ? '#fff' : 'var(--odb-fg)' }}">
                        {{ $painel->name }}
                    </button>
                @endforeach
            </div>
        @endif

        @if ($atual?->description)
            <p class="odb-lead">{{ $atual->description }}</p>
        @endif

        <div class="odb-grid">
            @foreach ($blocks as $bloco)
                <section class="odb-w" style="--span: {{ $bloco['size'] }}" data-block="{{ $bloco['key'] }}">
                    <div class="odb-wh">
                        <h3>{{ $bloco['title'] }}<small>{{ $bloco['subtitle'] }}</small></h3>
                    </div>
                    @include('dashboards.types.'.$bloco['payload']['type'], ['p' => $bloco['payload']])
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>

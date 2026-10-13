<div class="mx-auto flex min-h-screen max-w-md flex-col md:h-full md:min-h-0 md:overflow-y-auto">
    <header class="flex items-center justify-between px-5 pb-2 pt-6">
        @if ($ativo)
            <button type="button" wire:click="trocarVeiculo" class="text-xs font-bold tracking-widest text-slate-400">← VOLTAR</button>
        @else
            <a href="{{ $this->paraHub() }}" class="text-xs font-bold tracking-widest text-slate-400">← LOGÍSTICA</a>
        @endif
        <h1 class="text-xs font-bold tracking-widest text-slate-400">MARCAR KM</h1>
    </header>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-24">
        @if ($mensagem)<div class="rounded-2xl bg-emerald-500/15 p-4 text-sm font-bold text-emerald-400">✅ {{ $mensagem }}</div>@endif
        @if ($erro)<div class="rounded-2xl bg-red-500/15 p-4 text-sm font-bold text-red-400">{{ $erro }}</div>@endif

        @if (! $ativo)
            @include('livewire.logistica.escolher-veiculo', ['titulo' => 'TOQUE NO VEÍCULO'])
        @else
            <div class="rounded-2xl bg-slate-900 p-4">
                <h2 class="text-lg font-extrabold leading-tight text-white">{{ $ativo->name }}</h2>
                <p class="mt-1 text-sm font-medium text-slate-400">Placa: {{ $ativo->placa ?? '—' }} · Último km: {{ number_format((float) $ativo->odometro_atual, 0, ',', '.') }}</p>
            </div>

            <form wire:submit="marcar" class="space-y-3 rounded-2xl bg-slate-900 p-4">
                <label class="block text-xs font-bold tracking-wider text-slate-400">KM NO PAINEL AGORA
                    <input type="number" inputmode="numeric" wire:model="odometro" class="mt-1 min-h-[3rem] w-full rounded-xl bg-slate-800 px-3 text-lg font-bold text-white">
                </label>
                <label class="block text-xs font-bold tracking-wider text-slate-400">JUSTIFICATIVA (SÓ SE O KM FOR MENOR QUE O ÚLTIMO, EX.: PAINEL TROCADO)
                    <input type="text" wire:model="justificativa" class="mt-1 min-h-[3rem] w-full rounded-xl bg-slate-800 px-3 text-base text-white">
                </label>
                <button type="submit" class="flex min-h-[3.25rem] w-full items-center justify-center rounded-xl bg-emerald-500 text-sm font-bold text-slate-950 active:bg-emerald-600">MARCAR KM</button>
            </form>

            @if ($leituras->isNotEmpty())
                <div class="rounded-2xl bg-slate-900 p-4">
                    <p class="text-xs font-bold tracking-widest text-slate-400">ÚLTIMAS MARCAÇÕES</p>
                    @foreach ($leituras as $l)
                        <p class="mt-2 text-sm text-slate-200">{{ number_format($l->odometro, 0, ',', '.') }} km <span class="text-xs text-slate-400">· {{ $l->lido_em->format('d/m/Y H:i') }} · {{ str_replace('_', ' ', $l->origem) }}</span></p>
                    @endforeach
                </div>
            @endif
        @endif
    </main>
</div>

<div class="mx-auto flex min-h-screen max-w-md flex-col bg-zinc-50 text-zinc-900 md:h-full md:min-h-0 md:overflow-y-auto">
    <header class="mb-4 flex items-center justify-between rounded-b-3xl bg-gradient-to-r from-blue-600 to-blue-500 px-5 pb-5 pt-6 text-white shadow-md">
        @if ($ativo)
            <button type="button" wire:click="trocarVeiculo" class="text-xs font-bold tracking-widest text-blue-100">← VOLTAR</button>
        @else
            <a href="{{ $this->paraHub() }}" class="text-xs font-bold tracking-widest text-blue-100">← LOGÍSTICA</a>
        @endif
        <h1 class="text-xs font-bold tracking-widest text-blue-100">MARCAR KM</h1>
    </header>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-24">
        @if ($mensagem)<div class="rounded-xl bg-emerald-100 p-4 text-sm font-bold text-emerald-700">✅ {{ $mensagem }}</div>@endif
        @if ($erro)<div class="rounded-xl bg-red-100 p-4 text-sm font-bold text-red-600">{{ $erro }}</div>@endif

        @if (! $ativo)
            @include('livewire.logistica.escolher-veiculo', ['titulo' => 'TOQUE NO VEÍCULO'])
        @else
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <h2 class="text-lg font-extrabold leading-tight text-zinc-900">{{ $ativo->name }}</h2>
                <p class="mt-1 text-sm font-medium text-zinc-600">Placa: {{ $ativo->placa ?? '—' }} · Último km: {{ number_format((float) $ativo->odometro_atual, 0, ',', '.') }}</p>
            </div>

            <form wire:submit="marcar" class="space-y-3 rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <label class="block text-xs font-bold tracking-wider text-zinc-600">KM NO PAINEL AGORA
                    <input type="number" inputmode="numeric" wire:model="odometro" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-lg font-bold text-zinc-900 border border-zinc-300">
                </label>
                <label class="block text-xs font-bold tracking-wider text-zinc-600">JUSTIFICATIVA (SÓ SE O KM FOR MENOR QUE O ÚLTIMO, EX.: PAINEL TROCADO)
                    <input type="text" wire:model="justificativa" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                </label>
                <button type="submit" class="flex min-h-[3.25rem] w-full items-center justify-center rounded-xl bg-emerald-600 text-sm font-bold text-white active:bg-emerald-600">MARCAR KM</button>
            </form>

            @if ($leituras->isNotEmpty())
                <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                    <p class="text-xs font-bold tracking-widest text-zinc-600">ÚLTIMAS MARCAÇÕES</p>
                    @foreach ($leituras as $l)
                        <p class="mt-2 text-sm text-zinc-800">{{ number_format($l->odometro, 0, ',', '.') }} km <span class="text-xs text-zinc-600">· {{ $l->lido_em->format('d/m/Y H:i') }} · {{ str_replace('_', ' ', $l->origem) }}</span></p>
                    @endforeach
                </div>
            @endif
        @endif
    </main>
</div>

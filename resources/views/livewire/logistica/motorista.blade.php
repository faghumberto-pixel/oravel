<div class="mx-auto flex min-h-screen max-w-md flex-col md:h-full md:min-h-0 md:overflow-y-auto">
    <header class="flex items-center justify-between px-5 pb-2 pt-6">
        @if ($ativo)
            <button type="button" wire:click="trocarVeiculo" class="text-xs font-bold tracking-widest text-slate-400">← VOLTAR</button>
        @else
            <a href="{{ $this->paraHub() }}" class="text-xs font-bold tracking-widest text-slate-400">← LOGÍSTICA</a>
        @endif
        <h1 class="text-xs font-bold tracking-widest text-slate-400">MOTORISTA DO VEÍCULO</h1>
    </header>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-24">
        @if ($mensagem)<div class="rounded-2xl bg-emerald-500/15 p-4 text-sm font-bold text-emerald-400">✅ {{ $mensagem }}</div>@endif
        @if ($erro)<div class="rounded-2xl bg-red-500/15 p-4 text-sm font-bold text-red-400">{{ $erro }}</div>@endif

        @if (! $ativo)
            @include('livewire.logistica.escolher-veiculo', ['titulo' => 'TOQUE NO VEÍCULO'])
        @else
            <div class="rounded-2xl bg-slate-900 p-4">
                <h2 class="text-lg font-extrabold leading-tight text-white">{{ $ativo->name }}</h2>
                <p class="mt-1 text-sm font-medium text-slate-400">Placa: {{ $ativo->placa ?? '—' }}</p>
                @if ($atual)
                    <p class="mt-2 text-sm text-slate-200">Motorista atual: <strong>{{ $atual->motorista?->name }}</strong></p>
                    <p class="text-xs text-slate-400">Desde {{ $atual->inicio->format('d/m/Y') }}</p>
                @else
                    <p class="mt-2 text-sm text-amber-400">Este veículo ainda não tem motorista titular.</p>
                @endif
            </div>

            <form wire:submit="atribuir" class="space-y-3 rounded-2xl bg-slate-900 p-4">
                <p class="text-xs font-bold tracking-widest text-slate-400">{{ $atual ? 'SUBSTITUIR MOTORISTA' : 'INCLUIR MOTORISTA' }}</p>
                <label class="block text-xs font-bold tracking-wider text-slate-400">NOVO MOTORISTA
                    <select wire:model="motoristaId" class="mt-1 min-h-[3rem] w-full rounded-xl bg-slate-800 px-3 text-base text-white">
                        <option value="">Escolha o motorista</option>
                        @foreach ($motoristas as $id => $nome)<option value="{{ $id }}">{{ $nome }}</option>@endforeach
                    </select>
                </label>
                @if (count($motoristas) === 0)
                    <p class="text-xs text-amber-400">Nenhum motorista cadastrado. Cadastre em Logística → Frota → Motoristas.</p>
                @endif
                <label class="block text-xs font-bold tracking-wider text-slate-400">A PARTIR DE
                    <input type="date" wire:model="inicio" class="mt-1 min-h-[3rem] w-full rounded-xl bg-slate-800 px-3 text-base text-white">
                </label>
                <label class="block text-xs font-bold tracking-wider text-slate-400">OBSERVAÇÕES
                    <textarea wire:model="observacoes" rows="2" class="mt-1 w-full rounded-xl bg-slate-800 px-3 py-2 text-base text-white"></textarea>
                </label>
                <button type="submit" class="flex min-h-[3.25rem] w-full items-center justify-center rounded-xl bg-emerald-500 text-sm font-bold text-slate-950 active:bg-emerald-600">{{ $atual ? 'SUBSTITUIR MOTORISTA' : 'INCLUIR MOTORISTA' }}</button>
            </form>

            @if ($atual)
                <button type="button" wire:click="encerrar" wire:confirm="Encerrar o vínculo e deixar o veículo sem motorista titular?" class="flex min-h-[3rem] w-full items-center justify-center rounded-xl border border-slate-600 text-sm font-bold text-slate-300 active:bg-slate-800">Encerrar vínculo (sem substituir)</button>
            @endif

            @if ($historico->isNotEmpty())
                <div class="rounded-2xl bg-slate-900 p-4">
                    <p class="text-xs font-bold tracking-widest text-slate-400">MOTORISTAS ANTERIORES</p>
                    @foreach ($historico as $h)
                        <p class="mt-2 text-sm text-slate-200">{{ $h->motorista?->name }} <span class="text-xs text-slate-400">· {{ $h->inicio->format('d/m/Y') }} a {{ $h->fim->format('d/m/Y') }}</span></p>
                    @endforeach
                </div>
            @endif
        @endif
    </main>
</div>

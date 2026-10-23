<div class="mx-auto flex min-h-screen max-w-md flex-col bg-zinc-50 text-zinc-900 md:h-full md:min-h-0 md:overflow-y-auto">
    <header class="mb-4 flex items-center justify-between rounded-b-3xl bg-gradient-to-r from-blue-600 to-blue-500 px-5 pb-5 pt-6 text-white shadow-md">
        @if ($ativo)
            <button type="button" wire:click="trocarVeiculo" class="text-xs font-bold tracking-widest text-blue-100">← VOLTAR</button>
        @else
            <a href="{{ $this->paraHub() }}" class="text-xs font-bold tracking-widest text-blue-100">← LOGÍSTICA</a>
        @endif
        <h1 class="text-xs font-bold tracking-widest text-blue-100">MOTORISTA DO VEÍCULO</h1>
    </header>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-24">
        @if ($mensagem)<div class="rounded-xl bg-emerald-100 p-4 text-sm font-bold text-emerald-700">✅ {{ $mensagem }}</div>@endif
        @if ($erro)<div class="rounded-xl bg-red-100 p-4 text-sm font-bold text-red-600">{{ $erro }}</div>@endif

        @if (! $ativo)
            @include('livewire.logistica.escolher-veiculo', ['titulo' => 'TOQUE NO VEÍCULO'])
        @else
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <h2 class="text-lg font-extrabold leading-tight text-zinc-900">{{ $ativo->name }}</h2>
                <p class="mt-1 text-sm font-medium text-zinc-600">Placa: {{ $ativo->placa ?? '—' }}</p>
                @if ($atual)
                    <p class="mt-2 text-sm text-zinc-800">Motorista atual: <strong>{{ $atual->motorista?->name }}</strong></p>
                    <p class="text-xs text-zinc-600">Desde {{ $atual->inicio->format('d/m/Y') }}</p>
                @else
                    <p class="mt-2 text-sm text-amber-700">Este veículo ainda não tem motorista titular.</p>
                @endif
            </div>

            <form wire:submit="atribuir" class="space-y-3 rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <p class="text-xs font-bold tracking-widest text-zinc-600">{{ $atual ? 'SUBSTITUIR MOTORISTA' : 'INCLUIR MOTORISTA' }}</p>
                <label class="block text-xs font-bold tracking-wider text-zinc-600">NOVO MOTORISTA
                    <select wire:model="motoristaId" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                        <option value="">Escolha o motorista</option>
                        @foreach ($motoristas as $id => $nome)<option value="{{ $id }}">{{ $nome }}</option>@endforeach
                    </select>
                </label>
                @if (count($motoristas) === 0)
                    <p class="text-xs text-amber-700">Nenhum motorista cadastrado. Cadastre em Logística → Frota → Motoristas.</p>
                @endif
                <label class="block text-xs font-bold tracking-wider text-zinc-600">A PARTIR DE
                    <input type="date" wire:model="inicio" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                </label>
                <label class="block text-xs font-bold tracking-wider text-zinc-600">OBSERVAÇÕES
                    <textarea wire:model="observacoes" rows="2" class="mt-1 w-full rounded-xl bg-zinc-100 px-3 py-2 text-base text-zinc-900 border border-zinc-300"></textarea>
                </label>
                <button type="submit" class="flex min-h-[3.25rem] w-full items-center justify-center rounded-xl bg-emerald-600 text-sm font-bold text-white active:bg-emerald-600">{{ $atual ? 'SUBSTITUIR MOTORISTA' : 'INCLUIR MOTORISTA' }}</button>
            </form>

            @if ($atual)
                <button type="button" wire:click="encerrar" wire:confirm="Encerrar o vínculo e deixar o veículo sem motorista titular?" class="flex min-h-[3rem] w-full items-center justify-center rounded-xl border border-zinc-300 text-sm font-bold text-zinc-700 active:bg-zinc-100">Encerrar vínculo (sem substituir)</button>
            @endif

            @if ($historico->isNotEmpty())
                <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                    <p class="text-xs font-bold tracking-widest text-zinc-600">MOTORISTAS ANTERIORES</p>
                    @foreach ($historico as $h)
                        <p class="mt-2 text-sm text-zinc-800">{{ $h->motorista?->name }} <span class="text-xs text-zinc-600">· {{ $h->inicio->format('d/m/Y') }} a {{ $h->fim->format('d/m/Y') }}</span></p>
                    @endforeach
                </div>
            @endif
        @endif
    </main>
</div>

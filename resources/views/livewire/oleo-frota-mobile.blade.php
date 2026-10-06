<div class="mx-auto flex min-h-screen max-w-md flex-col md:h-full md:min-h-0 md:overflow-y-auto">
    <header class="flex items-center justify-between px-5 pb-2 pt-6">
        <a href="{{ route('assets.dossier.mobile', ['assetId' => $ativo->id]) }}" class="text-xs font-bold tracking-widest text-zinc-400">← VOLTAR</a>
        <h1 class="text-xs font-bold tracking-widest text-zinc-400">ÓLEO DA FROTA</h1>
    </header>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-24">
        <div class="rounded-2xl bg-zinc-900 p-4">
            <h2 class="text-lg font-extrabold leading-tight text-white">{{ $ativo->name }}</h2>
            <p class="mt-1 text-sm font-medium text-zinc-400">Placa: {{ $ativo->placa ?? '—' }} · Odômetro: {{ number_format((float) $ativo->odometro_atual, 0, ',', '.') }} km</p>
            <p class="mt-2 text-sm font-bold {{ $status['situacao'] === 'vencida' ? 'text-red-400' : ($status['situacao'] === 'proxima' ? 'text-amber-400' : 'text-zinc-300') }}">{{ $status['mensagem'] }}</p>
            @if ($consumo)
                <p class="mt-1 text-xs font-bold text-amber-400">⚠ Consumo anormal: {{ $consumo['litros'] }} L repostos em {{ $consumo['km'] }} km desde a última troca.</p>
            @endif
        </div>

        @if ($mensagem)
            <div class="rounded-2xl bg-emerald-500/15 p-4 text-sm font-bold text-emerald-400">✅ {{ $mensagem }}</div>
        @endif
        @if ($erro)
            <div class="rounded-2xl bg-red-500/15 p-4 text-sm font-bold text-red-400">{{ $erro }}</div>
        @endif

        <form wire:submit="salvar" class="space-y-3 rounded-2xl bg-zinc-900 p-4">
            <div class="grid grid-cols-2 gap-2">
                <label class="flex min-h-[3rem] items-center justify-center rounded-xl text-sm font-bold {{ $tipo === 'troca' ? 'bg-emerald-500 text-zinc-950' : 'bg-zinc-800 text-zinc-300' }}">
                    <input type="radio" wire:model.live="tipo" value="troca" class="sr-only"> TROCA
                </label>
                <label class="flex min-h-[3rem] items-center justify-center rounded-xl text-sm font-bold {{ $tipo === 'reposicao' ? 'bg-blue-500 text-white' : 'bg-zinc-800 text-zinc-300' }}">
                    <input type="radio" wire:model.live="tipo" value="reposicao" class="sr-only"> REPOSIÇÃO
                </label>
            </div>
            <p class="text-xs text-zinc-400">Troca zera o contador e calcula a próxima. Reposição (completar nível) não zera.</p>

            <label class="block text-xs font-bold tracking-wider text-zinc-400">LITROS (INTEIROS)
                <input type="number" inputmode="numeric" step="1" min="1" wire:model="litros" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-800 px-3 text-base text-white">
            </label>
            <label class="block text-xs font-bold tracking-wider text-zinc-400">PRODUTO (ÓLEO)
                <input type="text" wire:model="produto" placeholder="Ex.: 15W40 CK-4" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-800 px-3 text-base text-white">
            </label>
            <label class="block text-xs font-bold tracking-wider text-zinc-400">ODÔMETRO (KM)
                <input type="number" inputmode="numeric" wire:model="odometro" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-800 px-3 text-base text-white">
            </label>

            @if (count($pecas) > 0)
                <label class="block text-xs font-bold tracking-wider text-zinc-400">ÓLEO NO ESTOQUE (OPCIONAL)
                    <select wire:model.live="pecaId" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-800 px-3 text-base text-white">
                        <option value="">Não descontar do estoque</option>
                        @foreach ($pecas as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($pecaId)
                    <label class="block text-xs font-bold tracking-wider text-zinc-400">ALMOXARIFADO
                        <select wire:model="almoxarifadoId" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-800 px-3 text-base text-white">
                            <option value="">Escolha</option>
                            @foreach ($almoxarifados as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            @endif

            <button type="submit" class="flex min-h-[3.25rem] w-full items-center justify-center rounded-xl bg-emerald-500 text-sm font-bold text-zinc-950 active:bg-emerald-600">REGISTRAR</button>
        </form>
    </main>
</div>

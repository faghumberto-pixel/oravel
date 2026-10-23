<div class="mx-auto flex min-h-screen max-w-md flex-col bg-zinc-50 text-zinc-900 md:h-full md:min-h-0 md:overflow-y-auto">
    <header class="mb-4 flex items-center justify-between rounded-b-3xl bg-gradient-to-r from-blue-600 to-blue-500 px-5 pb-5 pt-6 text-white shadow-md">
        <a href="{{ route('assets.dossier.mobile', ['assetId' => $ativo->id]) }}" class="text-xs font-bold tracking-widest text-blue-100">← VOLTAR</a>
        <h1 class="text-xs font-bold tracking-widest text-blue-100">ÓLEO DA FROTA</h1>
    </header>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-24">
        <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
            <h2 class="text-lg font-extrabold leading-tight text-zinc-900">{{ $ativo->name }}</h2>
            <p class="mt-1 text-sm font-medium text-zinc-600">Placa: {{ $ativo->placa ?? '—' }} · Odômetro: {{ number_format((float) $ativo->odometro_atual, 0, ',', '.') }} km</p>
            <p class="mt-2 text-sm font-bold {{ $status['situacao'] === 'vencida' ? 'text-red-600' : ($status['situacao'] === 'proxima' ? 'text-amber-700' : 'text-zinc-700') }}">{{ $status['mensagem'] }}</p>
            @if ($consumo)
                <p class="mt-1 text-xs font-bold text-amber-700">⚠ Consumo anormal: {{ $consumo['litros'] }} L repostos em {{ $consumo['km'] }} km desde a última troca.</p>
            @endif
        </div>

        @if ($mensagem)
            <div class="rounded-xl bg-emerald-100 p-4 text-sm font-bold text-emerald-700">✅ {{ $mensagem }}</div>
        @endif
        @if ($erro)
            <div class="rounded-xl bg-red-100 p-4 text-sm font-bold text-red-600">{{ $erro }}</div>
        @endif

        <form wire:submit="salvar" class="space-y-3 rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
            <div class="grid grid-cols-2 gap-2">
                <label class="flex min-h-[3rem] items-center justify-center rounded-xl text-sm font-bold {{ $tipo === 'troca' ? 'bg-emerald-600 text-white' : 'bg-zinc-100 text-zinc-700' }}">
                    <input type="radio" wire:model.live="tipo" value="troca" class="sr-only"> TROCA
                </label>
                <label class="flex min-h-[3rem] items-center justify-center rounded-xl text-sm font-bold {{ $tipo === 'reposicao' ? 'bg-blue-600 text-white' : 'bg-zinc-100 text-zinc-700' }}">
                    <input type="radio" wire:model.live="tipo" value="reposicao" class="sr-only"> REPOSIÇÃO
                </label>
            </div>
            <p class="text-xs text-zinc-600">Troca zera o contador e calcula a próxima. Reposição (completar nível) não zera.</p>

            <label class="block text-xs font-bold tracking-wider text-zinc-600">LITROS (INTEIROS)
                <input type="number" inputmode="numeric" step="1" min="1" wire:model="litros" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900">
            </label>
            <label class="block text-xs font-bold tracking-wider text-zinc-600">PRODUTO (ÓLEO)
                <input type="text" wire:model="produto" placeholder="Ex.: 15W40 CK-4" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900">
            </label>
            <label class="block text-xs font-bold tracking-wider text-zinc-600">ODÔMETRO (KM)
                <input type="number" inputmode="numeric" wire:model="odometro" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900">
            </label>

            @if (count($pecas) > 0)
                <label class="block text-xs font-bold tracking-wider text-zinc-600">ÓLEO NO ESTOQUE (OPCIONAL)
                    <select wire:model.live="pecaId" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900">
                        <option value="">Não descontar do estoque</option>
                        @foreach ($pecas as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($pecaId)
                    <label class="block text-xs font-bold tracking-wider text-zinc-600">ALMOXARIFADO
                        <select wire:model="almoxarifadoId" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900">
                            <option value="">Escolha</option>
                            @foreach ($almoxarifados as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            @endif

            <button type="submit" class="flex min-h-[3.25rem] w-full items-center justify-center rounded-xl bg-emerald-600 text-sm font-bold text-white active:bg-emerald-600">REGISTRAR</button>
        </form>
    </main>
</div>

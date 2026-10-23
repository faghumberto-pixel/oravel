<div class="mx-auto flex min-h-screen max-w-md flex-col bg-zinc-50 text-zinc-900 md:h-full md:min-h-0 md:overflow-y-auto">
    <header class="mb-4 flex items-center justify-between rounded-b-3xl bg-gradient-to-r from-blue-600 to-blue-500 px-5 pb-5 pt-6 text-white shadow-md">
        @if ($ativo)
            <button type="button" wire:click="trocarVeiculo" class="text-xs font-bold tracking-widest text-blue-100">← VOLTAR</button>
        @else
            <a href="{{ $this->paraHub() }}" class="text-xs font-bold tracking-widest text-blue-100">← LOGÍSTICA</a>
        @endif
        <h1 class="text-xs font-bold tracking-widest text-blue-100">ABASTECIMENTO</h1>
    </header>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-24">
        @if ($mensagem)<div class="rounded-xl bg-emerald-100 p-4 text-sm font-bold text-emerald-700">✅ {{ $mensagem }}</div>@endif
        @if ($erro)<div class="rounded-xl bg-red-100 p-4 text-sm font-bold text-red-600">{{ $erro }}</div>@endif

        @if (! $ativo)
            @include('livewire.logistica.escolher-veiculo', ['titulo' => 'TOQUE NO VEÍCULO QUE ABASTECEU'])
        @else
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <h2 class="text-lg font-extrabold leading-tight text-zinc-900">{{ $ativo->name }}</h2>
                <p class="mt-1 text-sm font-medium text-zinc-600">Placa: {{ $ativo->placa ?? '—' }} · Odômetro: {{ number_format((float) $ativo->odometro_atual, 0, ',', '.') }} km</p>
                @if ($ultimo)
                    <p class="mt-1 text-xs text-zinc-600">Último abastecimento: {{ $ultimo->abastecido_em->format('d/m/Y H:i') }} · {{ number_format((float) $ultimo->litros, 1, ',', '.') }} L @if ($ultimo->consumo_km_l) · {{ number_format((float) $ultimo->consumo_km_l, 2, ',', '.') }} km/l @endif</p>
                @endif
            </div>

            <form wire:submit="registrar" class="space-y-3 rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <label class="block text-xs font-bold tracking-wider text-zinc-600">DATA E HORA
                    <input type="datetime-local" wire:model="dataHora" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                </label>
                <label class="block text-xs font-bold tracking-wider text-zinc-600">ODÔMETRO NO ABASTECIMENTO (KM)
                    <input type="number" inputmode="numeric" wire:model="odometro" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                </label>
                <label class="block text-xs font-bold tracking-wider text-zinc-600">JUSTIFICATIVA (SÓ SE O KM FOR MENOR QUE O ÚLTIMO)
                    <input type="text" wire:model="justificativaOdometro" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                </label>
                <label class="block text-xs font-bold tracking-wider text-zinc-600">COMBUSTÍVEL
                    <select wire:model="combustivel" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                        @foreach ($combustiveis as $k => $nome)<option value="{{ $k }}">{{ $nome }}</option>@endforeach
                    </select>
                </label>
                <label class="block text-xs font-bold tracking-wider text-zinc-600">LITROS
                    <input type="number" inputmode="decimal" step="0.01" wire:model="litros" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                </label>
                <label class="block text-xs font-bold tracking-wider text-zinc-600">VALOR TOTAL (R$)
                    <input type="number" inputmode="decimal" step="0.01" wire:model="valorTotal" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                </label>
                <label class="flex items-center gap-3 text-sm font-bold text-zinc-800">
                    <input type="checkbox" wire:model="tanqueCheio" class="h-5 w-5 rounded"> Encheu o tanque
                </label>
                <label class="block text-xs font-bold tracking-wider text-zinc-600">ORIGEM
                    <select wire:model="origem" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                        @foreach ($origens as $k => $nome)<option value="{{ $k }}">{{ $nome }}</option>@endforeach
                    </select>
                </label>
                <label class="block text-xs font-bold tracking-wider text-zinc-600">POSTO
                    <input type="text" wire:model="posto" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                </label>
                <label class="block text-xs font-bold tracking-wider text-zinc-600">NOTA FISCAL / CUPOM
                    <input type="text" wire:model="notaFiscal" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                </label>
                <label class="block text-xs font-bold tracking-wider text-zinc-600">MOTORISTA
                    <select wire:model="motoristaId" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                        <option value="">Não informar</option>
                        @foreach ($motoristas as $id => $nome)<option value="{{ $id }}">{{ $nome }}</option>@endforeach
                    </select>
                </label>
                <label class="block text-xs font-bold tracking-wider text-zinc-600">OBSERVAÇÕES
                    <textarea wire:model="observacoes" rows="2" class="mt-1 w-full rounded-xl bg-zinc-100 px-3 py-2 text-base text-zinc-900 border border-zinc-300"></textarea>
                </label>
                <button type="submit" class="flex min-h-[3.25rem] w-full items-center justify-center rounded-xl bg-emerald-600 text-sm font-bold text-white active:bg-emerald-600">REGISTRAR ABASTECIMENTO</button>
            </form>
        @endif
    </main>
</div>

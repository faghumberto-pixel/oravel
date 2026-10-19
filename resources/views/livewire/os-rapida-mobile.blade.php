{{-- OS rápida (campo): equipamento da lista + o que aconteceu; depois segue para o modo campo da O.S. (cronômetro, fotos, assinatura). --}}
<div class="absolute inset-0 mx-auto flex max-w-md flex-col overflow-y-auto bg-slate-950 pb-8 text-white">
    <header class="sticky top-0 z-40 border-b border-slate-800 bg-slate-900/95 px-5 py-4 backdrop-blur">
        <a href="{{ route('campo.home') }}" class="text-sm font-semibold text-slate-400">‹ Voltar</a>
        <h1 class="mt-1 text-2xl font-black">OS rápida</h1>
        <p class="text-sm text-slate-400">Abra a ordem em poucos toques. Fotos e assinatura vêm no passo seguinte.</p>
    </header>

    <form wire:submit="criar" class="space-y-5 px-5 pt-5">
        <div>
            <label class="text-sm font-bold text-slate-200">1. Equipamento</label>
            <input type="search" wire:model.live.debounce.300ms="busca" placeholder="Buscar por nome, etiqueta ou patrimônio"
                class="mt-2 w-full rounded-2xl border-0 bg-slate-800 p-4 text-base text-white placeholder-slate-500" />
            <div class="mt-2 max-h-64 space-y-2 overflow-y-auto" data-testid="lista-equipamentos">
                @forelse ($this->equipamentos as $e)
                    <button type="button" wire:click="escolher('{{ $e->id }}')" wire:key="eq-{{ $e->id }}"
                        class="flex w-full items-center justify-between rounded-2xl p-4 text-left {{ $assetId === $e->id ? 'bg-orange-600' : 'bg-slate-800' }}">
                        <span class="text-base font-semibold">{{ $e->name }}</span>
                        <span class="text-xs text-white/70">{{ $e->tag ?: $e->patrimonio }}</span>
                    </button>
                @empty
                    <p class="rounded-2xl bg-slate-800 p-4 text-sm text-slate-400">Nenhum equipamento encontrado.</p>
                @endforelse
            </div>
            @error('assetId') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="text-sm font-bold text-slate-200" for="clientId">2. Cliente (se houver)</label>
            <select id="clientId" wire:model="clientId" class="mt-2 w-full rounded-2xl border-0 bg-slate-800 p-4 text-base text-white">
                <option value="">Sem cliente</option>
                @foreach ($this->clientes as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-sm font-bold text-slate-200" for="local">3. Local</label>
            <input id="local" type="text" wire:model="local" placeholder="Onde o equipamento está"
                class="mt-2 w-full rounded-2xl border-0 bg-slate-800 p-4 text-base text-white placeholder-slate-500" />
        </div>

        <div>
            <span class="text-sm font-bold text-slate-200">4. Tipo</span>
            <div class="mt-2 grid grid-cols-2 gap-3">
                @foreach ([\App\Models\MaintenanceOrder::TYPE_CORRECTIVE => 'Corretiva', \App\Models\MaintenanceOrder::TYPE_PREVENTIVE => 'Preventiva'] as $valor => $rotulo)
                    <button type="button" wire:click="$set('tipo', '{{ $valor }}')"
                        class="rounded-2xl p-4 text-base font-bold {{ $tipo === $valor ? 'bg-orange-600' : 'bg-slate-800' }}">{{ $rotulo }}</button>
                @endforeach
            </div>
            @if ($tipo === \App\Models\MaintenanceOrder::TYPE_CORRECTIVE)
                <p class="mt-2 text-xs text-slate-400">Corretiva marca o equipamento como parado até a ordem ser concluída.</p>
            @endif
        </div>

        <div>
            <label class="text-sm font-bold text-slate-200" for="descricao">5. O que está acontecendo ou o que foi feito</label>
            <textarea id="descricao" wire:model="descricao" rows="4"
                class="mt-2 w-full rounded-2xl border-0 bg-slate-800 p-4 text-base text-white placeholder-slate-500"></textarea>
            @error('descricao') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="min-h-14 w-full rounded-2xl bg-orange-600 p-4 text-lg font-black uppercase tracking-wide text-white active:scale-95">
            Abrir ordem e continuar
        </button>
    </form>
</div>

{{-- Checklist do grupo do equipamento (campo): itens impeditivos e de alerta do grupo. Reprovar item impeditivo abre OS corretiva. --}}
<div class="absolute inset-0 mx-auto flex max-w-md flex-col overflow-y-auto bg-slate-950 pb-8 text-white">
    <header class="sticky top-0 z-40 border-b border-slate-800 bg-slate-900/95 px-5 py-4 backdrop-blur">
        <a href="{{ route('campo.home') }}" class="text-sm font-semibold text-slate-400">‹ Voltar</a>
        <h1 class="mt-1 text-2xl font-black">Checklist do equipamento</h1>
        <p class="text-sm text-slate-400">Os itens vêm do grupo do equipamento. Item impeditivo reprovado abre uma OS.</p>
    </header>

    @if ($resumo)
        <div class="space-y-4 px-5 pt-5" data-testid="resumo">
            <div class="rounded-2xl bg-slate-800 p-5">
                <p class="text-sm text-slate-400">{{ $this->ativo?->name }}</p>
                <p class="mt-1 text-xl font-black">Aptidão: {{ $this->rotuloStatus($resumo['status']) }}</p>
                <p class="mt-2 text-sm text-slate-300">{{ $resumo['registrados'] }} item(ns) registrado(s).</p>
                @if ($resumo['reprovados'])
                    <p class="mt-2 text-sm text-red-300">Reprovado: {{ implode(', ', $resumo['reprovados']) }}</p>
                @endif
                @if ($resumo['os'])
                    <p class="mt-2 text-sm text-orange-300">OS aberta: {{ implode(', ', $resumo['os']) }}</p>
                @endif
            </div>
            <button type="button" wire:click="trocar" class="min-h-14 w-full rounded-2xl bg-orange-600 p-4 text-lg font-black uppercase tracking-wide text-white active:scale-95">
                Outro equipamento
            </button>
        </div>
    @elseif (! $assetId)
        <div class="space-y-3 px-5 pt-5">
            <label class="text-sm font-bold text-slate-200">Equipamento</label>
            <input type="search" wire:model.live.debounce.300ms="busca" placeholder="Buscar por nome, etiqueta ou patrimônio"
                class="w-full rounded-2xl border-0 bg-slate-800 p-4 text-base text-white placeholder-slate-500" />
            <div class="max-h-96 space-y-2 overflow-y-auto" data-testid="lista-equipamentos">
                @forelse ($this->equipamentos as $e)
                    <button type="button" wire:click="escolher('{{ $e->id }}')" wire:key="eq-{{ $e->id }}"
                        class="flex w-full items-center justify-between rounded-2xl bg-slate-800 p-4 text-left">
                        <span class="text-base font-semibold">{{ $e->name }}</span>
                        <span class="text-xs text-white/70">{{ $e->tag ?: $e->patrimonio }}</span>
                    </button>
                @empty
                    <p class="rounded-2xl bg-slate-800 p-4 text-sm text-slate-400">Nenhum equipamento encontrado.</p>
                @endforelse
            </div>
        </div>
    @else
        <div class="space-y-4 px-5 pt-5">
            <div class="flex items-center justify-between rounded-2xl bg-slate-800 p-4">
                <span class="text-base font-semibold">{{ $this->ativo?->name }}</span>
                <button type="button" wire:click="trocar" class="text-sm font-semibold text-orange-400">Trocar</button>
            </div>

            @forelse ($this->itens as $item)
                @php($marcado = $resultados[$item->id] ?? null)
                <div class="rounded-2xl bg-slate-800 p-4" wire:key="item-{{ $item->id }}" data-testid="item-checklist">
                    <div class="flex items-start justify-between gap-3">
                        <span class="text-base font-semibold">{{ $item->item?->nome ?? $item->slug }}</span>
                        <span class="rounded-full px-2 py-1 text-xs font-bold {{ $item->efeito === 'IMP' ? 'bg-red-900 text-red-200' : 'bg-yellow-900 text-yellow-200' }}">
                            {{ $item->efeito === 'IMP' ? 'Impeditivo' : 'Alerta' }}
                        </span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <button type="button" wire:click="marcar('{{ $item->id }}', 'ok')"
                            class="rounded-2xl p-3 text-base font-bold {{ $marcado === 'ok' ? 'bg-green-600' : 'bg-slate-700' }}">OK</button>
                        <button type="button" wire:click="marcar('{{ $item->id }}', 'reprovado')"
                            class="rounded-2xl p-3 text-base font-bold {{ $marcado === 'reprovado' ? 'bg-red-600' : 'bg-slate-700' }}">Reprovado</button>
                    </div>
                    @if ($marcado === 'reprovado')
                        <textarea wire:model="observacoes.{{ $item->id }}" rows="2" placeholder="O que foi encontrado (opcional)"
                            class="mt-3 w-full rounded-2xl border-0 bg-slate-700 p-3 text-base text-white placeholder-slate-400"></textarea>
                    @endif
                </div>
            @empty
                <p class="rounded-2xl bg-slate-800 p-4 text-sm text-slate-400">Este equipamento não tem checklist: falta o grupo de equipamento no cadastro (cadastro incompleto).</p>
            @endforelse

            @error('respostas') <p class="text-sm text-red-400">{{ $message }}</p> @enderror

            @if ($this->itens->isNotEmpty())
                <button type="button" wire:click="enviar" class="min-h-14 w-full rounded-2xl bg-orange-600 p-4 text-lg font-black uppercase tracking-wide text-white active:scale-95">
                    Enviar checklist
                </button>
            @endif
        </div>
    @endif
</div>

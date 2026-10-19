{{-- Leads no celular: carteira do vendedor, retornos de hoje e registro de contato com próximo passo. --}}
<div class="absolute inset-0 mx-auto flex max-w-md flex-col overflow-y-auto bg-slate-950 pb-8 text-white">
    <header class="sticky top-0 z-40 border-b border-slate-800 bg-slate-900/95 px-5 py-4 backdrop-blur">
        <a href="{{ route('campo.home') }}" class="text-sm font-semibold text-slate-400">‹ Voltar</a>
        <h1 class="mt-1 text-2xl font-black">Meus leads</h1>
    </header>

    @if (session('ok'))
        <p class="mx-5 mt-4 rounded-2xl bg-emerald-500/15 p-4 text-base font-semibold text-emerald-300" data-testid="aviso-ok">{{ session('ok') }}</p>
    @endif

    @if ($this->lead)
        @php($l = $this->lead)
        <section class="space-y-4 px-5 pt-5" data-testid="detalhe-lead">
            <button type="button" wire:click="fechar" class="text-sm font-semibold text-slate-400">‹ Lista de leads</button>
            <div class="rounded-3xl bg-pink-600 p-5">
                <p class="text-xl font-black">{{ $l->name }}</p>
                @if ($l->company_name)<p class="text-sm text-white/80">{{ $l->company_name }}</p>@endif
                <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-white/70">{{ $l->etapaNome() }}</p>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    @if ($l->phone)<a href="tel:{{ preg_replace('/\D/', '', $l->phone) }}" class="rounded-2xl bg-white/20 p-4 text-center text-base font-bold">Ligar</a>@endif
                    @if ($l->whatsapp ?: $l->phone)<a href="https://wa.me/55{{ preg_replace('/\D/', '', $l->whatsapp ?: $l->phone) }}" class="rounded-2xl bg-white/20 p-4 text-center text-base font-bold">WhatsApp</a>@endif
                </div>
            </div>

            <form wire:submit="registrar" class="space-y-4 rounded-3xl bg-slate-800 p-5">
                <p class="text-lg font-black">Registrar contato</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['telefone' => 'Ligação', 'whatsapp' => 'WhatsApp', 'email' => 'E-mail', 'presencial' => 'Visita'] as $valor => $rotulo)
                        <button type="button" wire:click="$set('canal', '{{ $valor }}')"
                            class="rounded-2xl p-3 text-base font-bold {{ $canal === $valor ? 'bg-pink-600' : 'bg-slate-700' }}">{{ $rotulo }}</button>
                    @endforeach
                </div>
                <div>
                    <label class="text-sm font-bold text-slate-200" for="resumo">Como foi?</label>
                    <textarea id="resumo" wire:model="resumo" rows="3" class="mt-2 w-full rounded-2xl border-0 bg-slate-700 p-4 text-base text-white"></textarea>
                    @error('resumo') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-bold text-slate-200" for="proximoPasso">Próximo passo</label>
                    <input id="proximoPasso" type="text" wire:model="proximoPasso" placeholder="Ex.: enviar proposta" class="mt-2 w-full rounded-2xl border-0 bg-slate-700 p-4 text-base text-white placeholder-slate-400" />
                </div>
                <div>
                    <label class="text-sm font-bold text-slate-200" for="proximoRetorno">Retornar em</label>
                    <input id="proximoRetorno" type="date" wire:model="proximoRetorno" class="mt-2 w-full rounded-2xl border-0 bg-slate-700 p-4 text-base text-white" />
                    @error('proximoRetorno') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="min-h-14 w-full rounded-2xl bg-pink-600 p-4 text-lg font-black uppercase tracking-wide active:scale-95">Salvar contato</button>
            </form>

            @if ($l->interactions->isNotEmpty())
                <div class="space-y-2">
                    <p class="text-sm font-bold uppercase tracking-wide text-slate-400">Últimos contatos</p>
                    @foreach ($l->interactions as $i)
                        <div class="rounded-2xl bg-slate-800 p-4 text-sm">
                            <p class="text-xs text-slate-400">{{ $i->contact_date?->format('d/m H:i') }} · {{ $i->channel }}</p>
                            <p class="mt-1">{{ $i->summary }}</p>
                            @if ($i->next_action)<p class="mt-1 text-xs text-pink-300">Próximo: {{ $i->next_action }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @else
        <div class="space-y-4 px-5 pt-5">
            <div class="grid grid-cols-2 gap-3">
                <button type="button" wire:click="$set('filtro', 'todos')" class="rounded-2xl p-4 text-base font-bold {{ $filtro === 'todos' ? 'bg-pink-600' : 'bg-slate-800' }}">Todos</button>
                <button type="button" wire:click="$set('filtro', 'retornos')" class="rounded-2xl p-4 text-base font-bold {{ $filtro === 'retornos' ? 'bg-pink-600' : 'bg-slate-800' }}">Retornos de hoje ({{ $this->retornos }})</button>
            </div>
            <input type="search" wire:model.live.debounce.300ms="busca" placeholder="Buscar por nome, empresa ou telefone" class="w-full rounded-2xl border-0 bg-slate-800 p-4 text-base text-white placeholder-slate-500" />

            <div class="space-y-3" data-testid="lista-leads">
                @forelse ($this->leads as $lead)
                    <button type="button" wire:click="abrir('{{ $lead->id }}')" wire:key="lead-{{ $lead->id }}" class="block w-full rounded-2xl bg-slate-800 p-4 text-left">
                        <span class="block text-base font-bold">{{ $lead->name }}</span>
                        <span class="block text-sm text-slate-400">{{ $lead->company_name }}</span>
                        <span class="mt-1 flex justify-between text-xs">
                            <span class="font-semibold text-pink-300">{{ $lead->etapaNome() }}</span>
                            @if ($lead->next_followup_date)<span class="{{ $lead->next_followup_date->isPast() ? 'text-red-300' : 'text-slate-400' }}">Retorno {{ $lead->next_followup_date->format('d/m') }}</span>@endif
                        </span>
                    </button>
                @empty
                    <p class="rounded-2xl bg-slate-800 p-4 text-base text-slate-400">Nenhum lead por aqui.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>

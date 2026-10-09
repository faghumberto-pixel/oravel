<x-filament-panels::page>
    @php
        $conversas = $this->conversas();
        $ativa = $this->ativa();
        $meuNumero = $this->seuNumero();
        $rotuloStatus = ['recebida' => '', 'enviando' => 'enviando…', 'enviada' => '✓ enviada', 'entregue' => '✓✓ entregue', 'lida' => '✓✓ lida', 'falhou' => 'não enviada'];
    @endphp

    {{-- Layout em CSS próprio: classes com valores entre colchetes não existem no CSS compilado do painel. --}}
    <style>
        .wa-grid { display: grid; grid-template-columns: 1fr; gap: 0.75rem; }
        @media (min-width: 1024px) { .wa-grid { grid-template-columns: 320px minmax(0, 1fr); } }
        .wa-lista { max-height: 60vh; }
        .wa-conversa { min-height: 60vh; display: flex; flex-direction: column; }
        .wa-bolha { max-width: 80%; }
    </style>

    <div wire:poll.8s class="space-y-3">
        <p class="text-xs text-gray-500">
            @if($meuNumero)
                Você envia e recebe pelo número <strong>{{ $meuNumero->display_phone ?: $meuNumero->phone_number_id }}</strong>{{ $meuNumero->user_id ? '' : ' (número da empresa)' }}.
            @else
                Você ainda não tem um número de WhatsApp ligado. Peça ao administrador em Configurações → WhatsApp da Empresa.
            @endif
        </p>

        <div class="wa-grid">
            {{-- Lista --}}
            <div class="space-y-2">
                <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Buscar por nome ou telefone" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900" />
                @if(auth()->user()->isAdmin())
                    <label class="flex items-center gap-2 text-xs text-gray-500"><input type="checkbox" wire:model.live="verTodas" class="rounded border-gray-300"> Ver as conversas de todos</label>
                @endif

                <div class="wa-lista divide-y divide-gray-100 overflow-y-auto rounded-xl border border-gray-200 bg-white dark:divide-white/5 dark:border-white/10 dark:bg-gray-900">
                    @forelse($conversas as $c)
                        <button type="button" wire:click="selecionar('{{ $c->id }}')" wire:key="wa-{{ $c->id }}"
                                class="block w-full px-3 py-2.5 text-left text-sm hover:bg-gray-50 dark:hover:bg-white/5 {{ $conversaId === $c->id ? 'bg-gray-50 dark:bg-white/5' : '' }}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate {{ $c->nao_lidas ? 'font-bold' : 'font-medium' }}">{{ $c->titulo() }}</span>
                                <span class="shrink-0 text-[10px] text-gray-400">{{ $c->ultima_mensagem_em?->format('d/m H:i') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-gray-500">
                                <span class="truncate">+{{ $c->telefone }}@if($c->responsavel && ($verTodas || $c->numero?->user_id === null)) · {{ $c->responsavel->name }}@endif@if(! $c->responsavel_user_id) · <strong class="text-amber-600">Na fila</strong>@endif</span>
                                @if($c->nao_lidas)<span class="rounded-full bg-primary-600 px-1.5 text-[10px] font-bold text-white">{{ $c->nao_lidas }}</span>@endif
                            </div>
                        </button>
                    @empty
                        <div class="p-6 text-center text-xs uppercase tracking-wide text-gray-400">Nenhuma conversa</div>
                    @endforelse
                </div>

                {{-- Nova conversa --}}
                @if($meuNumero)
                    <details class="rounded-xl border border-gray-200 bg-white p-3 text-sm dark:border-white/10 dark:bg-gray-900">
                        <summary class="cursor-pointer font-medium">Nova conversa</summary>
                        <div class="mt-3 space-y-2">
                            <select class="w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900" x-on:change="if ($event.target.value) { $wire.comecarComCliente($event.target.value); $event.target.value = '' }">
                                <option value="">Escolher um cliente…</option>
                                @foreach($this->clientesComWhatsapp() as $cl)
                                    <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                                @endforeach
                            </select>
                            <input type="text" wire:model="novoNome" placeholder="Nome (opcional)" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900" />
                            <input type="tel" wire:model="novoTelefone" placeholder="(19) 99999-0000" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900" />
                            <x-filament::button size="sm" wire:click="novaConversa">Abrir conversa</x-filament::button>
                        </div>
                    </details>
                @endif
            </div>

            {{-- Conversa --}}
            <div class="wa-conversa rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                @if($ativa)
                    <div class="border-b border-gray-100 px-4 py-3 dark:border-white/5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="text-sm font-bold">{{ $ativa->titulo() }}</h2>
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                @if(! $ativa->responsavel_user_id)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 font-bold uppercase text-amber-700">Na fila</span>
                                    <x-filament::button size="xs" wire:click="assumir('{{ $ativa->id }}')">Assumir esta conversa</x-filament::button>
                                @else
                                    <span class="text-gray-500">Atendendo: <strong>{{ $ativa->responsavel?->name }}</strong></span>
                                    @if(auth()->user()->isAdmin() || $ativa->responsavel_user_id === auth()->id())
                                        <select class="rounded-lg border-gray-300 py-1 text-xs dark:border-white/10 dark:bg-gray-900" x-on:change="if ($event.target.value) { $wire.transferir('{{ $ativa->id }}', $event.target.value); $event.target.value = '' }">
                                            <option value="">Transferir para…</option>
                                            @foreach($this->colegas() as $id => $nome)
                                                <option value="{{ $id }}">{{ $nome }}</option>
                                            @endforeach
                                        </select>
                                        @if($ativa->numero?->user_id === null)
                                            <x-filament::button size="xs" color="gray" wire:click="devolverFila('{{ $ativa->id }}')">Devolver à fila</x-filament::button>
                                        @endif
                                    @endif
                                @endif
                            </div>
                        </div>
                        <p class="text-xs text-gray-500">
                            +{{ $ativa->telefone }}
                            @if($ativa->client) · Cliente: <a class="text-primary-600 underline" href="{{ \App\Filament\Resources\ClientResource::getUrl('edit', ['record' => $ativa->client]) }}">{{ $ativa->client->name }}</a>@endif
                            @if($ativa->lead) · Lead: {{ $ativa->lead->name }}@endif
                        </p>
                    </div>

                    <div class="flex-1 space-y-2 overflow-y-auto px-4 py-3" style="max-height: 50vh">
                        @foreach($ativa->mensagens as $m)
                            <div class="flex {{ $m->direcao === 'saida' ? 'justify-end' : 'justify-start' }}" wire:key="msg-{{ $m->id }}">
                                <div class="wa-bolha rounded-2xl px-3 py-2 text-sm {{ $m->direcao === 'saida' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-gray-100' }}">
                                    <p class="whitespace-pre-line">{{ $m->corpo }}</p>
                                    <p class="mt-1 text-[10px] opacity-70">
                                        {{ $m->created_at->format('d/m H:i') }}@if($m->direcao === 'saida') · {{ $rotuloStatus[$m->status] ?? $m->status }}@if($m->enviadaPor) · {{ $m->enviadaPor->name }}@endif @endif
                                    </p>
                                    @if($m->status === 'falhou' && $m->erro)<p class="mt-1 text-[11px] font-semibold text-red-200">{{ $m->erro }}</p>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-gray-100 p-3 dark:border-white/5">
                        @if($ativa->janelaAberta())
                            <form wire:submit="enviar" class="flex gap-2">
                                <textarea wire:model="texto" rows="2" placeholder="Escreva sua mensagem…" class="flex-1 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900" x-on:keydown.enter.prevent="if (!$event.shiftKey) $wire.enviar()"></textarea>
                                <x-filament::button type="submit">Enviar</x-filament::button>
                            </form>
                        @else
                            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                <p class="text-gray-500">Passaram 24 horas desde a última mensagem do cliente: o WhatsApp só permite enviar um <strong>modelo aprovado</strong>.</p>
                                <x-filament::button size="sm" wire:click="enviarAbertura" icon="heroicon-o-paper-airplane">Enviar modelo de abertura</x-filament::button>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="flex flex-1 items-center justify-center p-10 text-center text-sm text-gray-400">Escolha uma conversa ao lado ou abra uma nova.</div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>

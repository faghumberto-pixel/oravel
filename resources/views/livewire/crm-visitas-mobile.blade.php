<div class="mx-auto flex min-h-screen max-w-md flex-col md:h-full md:min-h-0 md:overflow-y-auto">
    <header class="flex items-center justify-between px-5 pb-2 pt-6">
        <h1 class="text-xs font-bold tracking-widest text-zinc-400">MINHAS VISITAS</h1>
        <span class="text-xs font-bold tracking-wide text-zinc-300">SISTEMA {{ strtoupper(config('app.name', 'ORAVEL')) }}</span>
    </header>

    <div class="flex items-center gap-2 px-5 pb-3">
        <input type="date" wire:model.live="dia" class="rounded-xl border-0 bg-zinc-800 p-2 text-sm text-zinc-100" />
        <button type="button" wire:click="$set('mostrandoAvulsa', true)" class="ml-auto min-h-[2.5rem] rounded-xl bg-blue-500 px-3 text-xs font-bold text-zinc-950">+ VISITA AVULSA</button>
        <button type="button" wire:click="abrirDespesa()" class="min-h-[2.5rem] rounded-xl bg-zinc-800 px-3 text-xs font-bold text-zinc-100">+ DESPESA</button>
    </div>

    @if ($erro)
        <div class="mx-5 mb-2 rounded-xl bg-red-500/15 p-3 text-xs text-red-300">{{ $erro }}</div>
    @endif
    @foreach ($avisos as $aviso)
        <div class="mx-5 mb-2 rounded-xl bg-amber-500/15 p-3 text-xs text-amber-300">{{ $aviso }}</div>
    @endforeach

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-6">
        @forelse ($this->visitas as $v)
            @php
                $statusCor = match ($v->status) {'em_andamento' => 'bg-amber-500/20 text-amber-300', 'realizada' => 'bg-emerald-500/20 text-emerald-300', 'cancelada', 'nao_encontrou' => 'bg-red-500/20 text-red-300', default => 'bg-zinc-700 text-zinc-300'};
                $destino = $v->temCoordenadas() ? $v->latitude.','.$v->longitude : urlencode(trim(($v->endereco ?? '').' '.($v->cidade ?? '').' '.($v->uf ?? '')));
            @endphp
            <div class="rounded-2xl bg-zinc-900 p-4" wire:key="visita-{{ $v->id }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-bold text-zinc-100">{{ $v->alvoNome() }}</p>
                        <p class="text-[11px] text-zinc-400">{{ $v->agendada_para?->format('H:i') }} · {{ \App\Models\CrmVisita::objetivoLabels()[$v->objetivo] ?? $v->objetivo }}</p>
                        @if ($v->endereco)<p class="mt-1 text-[11px] text-zinc-500">{{ $v->endereco }} {{ $v->cidade }}</p>@endif
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $statusCor }}">{{ \App\Models\CrmVisita::statusLabels()[$v->status] ?? $v->status }}</span>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($destino)
                        <a href="https://www.google.com/maps/dir/?api=1&destination={{ $destino }}&travelmode=driving" target="_blank" rel="noopener" class="min-h-[2.5rem] rounded-xl bg-zinc-800 px-3 py-2 text-xs font-bold text-zinc-100">NAVEGAR</a>
                    @endif

                    @if ($v->status === 'agendada')
                        @if ($this->veiculos->isNotEmpty())
                            <select id="veiculo-{{ $v->id }}" class="rounded-xl border-0 bg-zinc-800 p-2 text-xs text-zinc-100">
                                <option value="">Sem veículo</option>
                                @foreach ($this->veiculos as $vei)<option value="{{ $vei->id }}" @selected($vei->id === $v->crm_veiculo_id)>{{ $vei->rotulo() }}</option>@endforeach
                            </select>
                        @endif
                        <button type="button"
                            onclick="const vei = document.getElementById('veiculo-{{ $v->id }}')?.value || null; if (!navigator.geolocation) { @this.checkin('{{ $v->id }}', null, null, vei); return; } navigator.geolocation.getCurrentPosition(p => @this.checkin('{{ $v->id }}', p.coords.latitude, p.coords.longitude, vei), () => @this.checkin('{{ $v->id }}', null, null, vei), {enableHighAccuracy: true, timeout: 10000});"
                            class="min-h-[2.5rem] flex-1 rounded-xl bg-amber-500 px-3 text-xs font-bold text-zinc-950">CHECK-IN</button>
                    @elseif ($v->status === 'em_andamento')
                        <button type="button" wire:click="abrirFinalizar('{{ $v->id }}')" class="min-h-[2.5rem] flex-1 rounded-xl bg-emerald-500 px-3 text-xs font-bold text-zinc-950">FINALIZAR</button>
                        <button type="button" wire:click="abrirDespesa('{{ $v->id }}')" class="min-h-[2.5rem] rounded-xl bg-zinc-800 px-3 text-xs font-bold text-zinc-100">+ DESPESA</button>
                    @endif
                </div>

                @if ($v->status === 'realizada' && $v->resultado)
                    <p class="mt-3 text-[11px] text-zinc-400">{{ $v->resultado }}</p>
                @endif
            </div>
        @empty
            <p class="pt-10 text-center text-sm text-zinc-500">Nenhuma visita neste dia.</p>
        @endforelse
    </main>

    {{-- Finalizar --}}
    @if ($finalizandoId)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-3">
            <div class="w-full max-w-md space-y-3 rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Finalizar visita</h3>
                <textarea wire:model="resultado" rows="3" placeholder="Resultado da visita" class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100"></textarea>
                <input type="number" wire:model="kmFim" placeholder="Km final do veículo (opcional)" class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100" />
                <input type="text" wire:model="proximoPasso" placeholder="Próximo passo" class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100" />
                <input type="date" wire:model="proximoContato" class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100" />
                <label class="flex items-center gap-2 text-xs text-zinc-300"><input type="checkbox" wire:model="naoEncontrou" /> Não encontrei ninguém</label>
                <div class="flex gap-2">
                    <button type="button" wire:click="cancelarFinalizar" class="min-h-[2.75rem] flex-1 rounded-xl bg-zinc-800 text-xs font-bold text-zinc-100">VOLTAR</button>
                    <button type="button" wire:click="finalizar" class="min-h-[2.75rem] flex-1 rounded-xl bg-emerald-500 text-xs font-bold text-zinc-950">CONCLUIR</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Despesa rápida --}}
    @if ($despesaVisitaId !== null)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-3">
            <div class="w-full max-w-md space-y-3 rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Lançar despesa</h3>
                <select wire:model="despesaTipo" class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100">
                    @foreach (\App\Models\CrmVisitaDespesa::tipoLabels() as $k => $r)<option value="{{ $k }}">{{ $r }}</option>@endforeach
                </select>
                <input type="number" step="0.01" wire:model="despesaValor" placeholder="Valor (R$)" class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100" />
                @error('despesaValor') <p class="text-[11px] text-red-400">{{ $message }}</p> @enderror
                <input type="text" wire:model="despesaDescricao" placeholder="Descrição" class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100" />
                <input type="file" wire:model="comprovante" accept="image/*,application/pdf" capture="environment" class="w-full text-xs text-zinc-300" />
                <div class="flex gap-2">
                    <button type="button" wire:click="fecharDespesa" class="min-h-[2.75rem] flex-1 rounded-xl bg-zinc-800 text-xs font-bold text-zinc-100">VOLTAR</button>
                    <button type="button" wire:click="lancarDespesa" class="min-h-[2.75rem] flex-1 rounded-xl bg-blue-500 text-xs font-bold text-zinc-950">LANÇAR</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Visita avulsa --}}
    @if ($mostrandoAvulsa)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-3">
            <div class="w-full max-w-md space-y-3 rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Visita avulsa (agora)</h3>
                <select wire:model="avulsaContaId" class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100">
                    <option value="">Conta (opcional)</option>
                    @foreach ($this->contas as $id => $nome)<option value="{{ $id }}">{{ $nome }}</option>@endforeach
                </select>
                <input type="text" wire:model="avulsaNome" placeholder="Ou quem está visitando" class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100" />
                <div class="flex gap-2">
                    <button type="button" wire:click="$set('mostrandoAvulsa', false)" class="min-h-[2.75rem] flex-1 rounded-xl bg-zinc-800 text-xs font-bold text-zinc-100">VOLTAR</button>
                    <button type="button" wire:click="salvarAvulsa" class="min-h-[2.75rem] flex-1 rounded-xl bg-blue-500 text-xs font-bold text-zinc-950">CRIAR</button>
                </div>
            </div>
        </div>
    @endif
</div>

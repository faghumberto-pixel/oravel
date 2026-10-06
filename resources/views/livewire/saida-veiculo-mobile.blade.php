<div class="mx-auto flex min-h-screen max-w-md flex-col md:h-full md:min-h-0 md:overflow-y-auto">
    <header class="flex items-center justify-between px-5 pb-2 pt-6">
        @if ($ativo)
            <button type="button" wire:click="voltar" class="text-xs font-bold tracking-widest text-slate-400">← VOLTAR</button>
        @else
            <a href="/admin" class="text-xs font-bold tracking-widest text-slate-400">← INÍCIO</a>
        @endif
        <h1 class="text-xs font-bold tracking-widest text-slate-400">ENTRADA E SAÍDA</h1>
    </header>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-24">
        @if ($mensagem)
            <div class="rounded-2xl bg-emerald-500/15 p-4 text-sm font-bold text-emerald-400">✅ {{ $mensagem }}</div>
        @endif
        @if ($erro)
            <div class="rounded-2xl bg-red-500/15 p-4 text-sm font-bold text-red-400">{{ $erro }}</div>
        @endif

        @if (! $ativo)
            <p class="text-xs font-bold tracking-wider text-slate-400">LOGÍSTICA · TOQUE NO VEÍCULO</p>
            @forelse ($veiculos as $v)
                @php
                    $saidaAberta = $fora->get($v->id);
                @endphp
                <button type="button" wire:click="escolher('{{ $v->id }}')" class="flex w-full items-center justify-between rounded-2xl bg-slate-900 p-4 text-left active:bg-slate-800">
                    <span>
                        <span class="block text-base font-extrabold text-white">{{ $v->placa ?? $v->name }}</span>
                        <span class="block text-xs text-slate-400">{{ $v->name }}</span>
                        @if ($saidaAberta)
                            <span class="mt-1 block text-xs text-amber-400">Fora desde {{ $saidaAberta->saida_em->format('d/m H:i') }} · {{ $saidaAberta->condutor() }} · {{ $saidaAberta->destino }}</span>
                        @endif
                    </span>
                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $saidaAberta ? 'bg-amber-500/20 text-amber-400' : 'bg-emerald-500/20 text-emerald-400' }}">{{ $saidaAberta ? 'FORA' : 'NA BASE' }}</span>
                </button>
            @empty
                <p class="rounded-2xl bg-slate-900 p-4 text-sm text-slate-400">Nenhum veículo cadastrado.</p>
            @endforelse
        @else
            <div class="rounded-2xl bg-slate-900 p-4">
                <h2 class="text-lg font-extrabold leading-tight text-white">{{ $ativo->name }}</h2>
                <p class="mt-1 text-sm font-medium text-slate-400">Placa: {{ $ativo->placa ?? '—' }} · Odômetro: {{ number_format((float) $ativo->odometro_atual, 0, ',', '.') }} km</p>
                @if (! $aberta && ($bloqueio = $ativo->bloqueioChecklistFrota()))
                    <p class="mt-2 rounded-lg bg-red-500/15 px-3 py-2 text-xs font-bold text-red-400">🚫 Bloqueado pelo checklist desde {{ $bloqueio->concluido_em?->format('d/m H:i') }}. Não pode sair até ser liberado.</p>
                @endif
            </div>

            @if ($aberta)
                <div class="rounded-2xl bg-amber-500/10 p-4 text-sm text-slate-200">
                    <p class="font-bold text-amber-400">Saiu em {{ $aberta->saida_em->format('d/m/Y H:i') }}</p>
                    <p class="mt-1">{{ $aberta->condutor() }} · {{ $finalidades[$aberta->finalidade] ?? $aberta->finalidade }}</p>
                    <p>Destino: {{ $aberta->destino }}</p>
                    <p>Motivo: {{ $aberta->motivo }}</p>
                    <p>Km na saída: {{ number_format($aberta->odometro_saida, 0, ',', '.') }}</p>
                </div>

                <form wire:submit="entrada" class="space-y-3 rounded-2xl bg-slate-900 p-4">
                    <p class="text-xs font-bold tracking-widest text-slate-400">REGISTRAR ENTRADA</p>
                    @include('livewire.partials.saida-veiculo-campos-comuns', ['rotuloData' => 'DATA E HORA DA ENTRADA', 'rotuloKm' => 'ODÔMETRO NA ENTRADA (KM)'])
                    <label class="block text-xs font-bold tracking-wider text-slate-400">OBSERVAÇÕES (AVARIAS, OCORRÊNCIAS)
                        <textarea wire:model="observacoes" rows="2" class="mt-1 w-full rounded-xl bg-slate-800 px-3 py-2 text-base text-white"></textarea>
                    </label>
                    <button type="submit" class="flex min-h-[3.25rem] w-full items-center justify-center rounded-xl bg-blue-500 text-sm font-bold text-white active:bg-blue-600">REGISTRAR ENTRADA</button>
                </form>
            @else
                <form wire:submit="saida" class="space-y-3 rounded-2xl bg-slate-900 p-4">
                    <p class="text-xs font-bold tracking-widest text-slate-400">REGISTRAR SAÍDA</p>
                    <label class="block text-xs font-bold tracking-wider text-slate-400">FINALIDADE
                        <select wire:model="finalidade" class="mt-1 min-h-[3rem] w-full rounded-xl bg-slate-800 px-3 text-base text-white">
                            @foreach ($finalidades as $k => $nome)
                                <option value="{{ $k }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-xs font-bold tracking-wider text-slate-400">MOTORISTA
                        <select wire:model="motoristaId" class="mt-1 min-h-[3rem] w-full rounded-xl bg-slate-800 px-3 text-base text-white">
                            <option value="">Escolha o motorista</option>
                            @foreach ($motoristas as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if (count($motoristas) === 0)
                        <p class="text-xs text-amber-400">Nenhum motorista cadastrado. Cadastre em Logística → Frota → Motoristas.</p>
                    @endif
                    <label class="block text-xs font-bold tracking-wider text-slate-400">DESTINO
                        <input type="text" wire:model="destino" class="mt-1 min-h-[3rem] w-full rounded-xl bg-slate-800 px-3 text-base text-white">
                    </label>
                    <label class="block text-xs font-bold tracking-wider text-slate-400">MOTIVO
                        <textarea wire:model="motivo" rows="2" class="mt-1 w-full rounded-xl bg-slate-800 px-3 py-2 text-base text-white"></textarea>
                    </label>
                    @include('livewire.partials.saida-veiculo-campos-comuns', ['rotuloData' => 'DATA E HORA DA SAÍDA', 'rotuloKm' => 'ODÔMETRO NA SAÍDA (KM)'])
                    <button type="submit" class="flex min-h-[3.25rem] w-full items-center justify-center rounded-xl bg-emerald-500 text-sm font-bold text-slate-950 active:bg-emerald-600">REGISTRAR SAÍDA</button>
                </form>
            @endif
        @endif
    </main>
</div>

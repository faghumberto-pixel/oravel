<div class="mx-auto flex min-h-screen max-w-md flex-col bg-zinc-50 text-zinc-900 md:h-full md:min-h-0 md:overflow-y-auto">
    <header class="mb-4 flex items-center justify-between rounded-b-3xl bg-gradient-to-r from-blue-600 to-blue-500 px-5 pb-5 pt-6 text-white shadow-md">
        @if ($ativo)
            <button type="button" wire:click="voltar" class="text-xs font-bold tracking-widest text-blue-100">← VOLTAR</button>
        @else
            <a href="{{ $modoApp ? route('colab.logistica') : '/admin' }}" class="text-xs font-bold tracking-widest text-blue-100">← {{ $modoApp ? 'LOGÍSTICA' : 'INÍCIO' }}</a>
        @endif
        <h1 class="text-xs font-bold tracking-widest text-blue-100">ENTRADA E SAÍDA</h1>
    </header>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-24">
        @if ($mensagem)
            <div class="rounded-xl bg-emerald-100 p-4 text-sm font-bold text-emerald-700">✅ {{ $mensagem }}</div>
        @endif
        @if ($erro)
            <div class="rounded-xl bg-red-100 p-4 text-sm font-bold text-red-600">{{ $erro }}</div>
        @endif

        @if (! $ativo)
            <p class="text-xs font-bold tracking-wider text-zinc-600">LOGÍSTICA · TOQUE NO VEÍCULO</p>
            @forelse ($veiculos as $v)
                @php
                    $saidaAberta = $fora->get($v->id);
                @endphp
                <button type="button" wire:click="escolher('{{ $v->id }}')" class="flex w-full items-center justify-between rounded-xl bg-white p-4 text-left active:bg-zinc-100 border border-zinc-200 shadow-sm">
                    <span>
                        <span class="block text-base font-extrabold text-zinc-900">{{ $v->placa ?? $v->name }}</span>
                        <span class="block text-xs text-zinc-600">{{ $v->name }}</span>
                        @if ($saidaAberta)
                            <span class="mt-1 block text-xs text-amber-700">Em trânsito desde {{ $saidaAberta->saida_em->format('d/m H:i') }} · {{ $saidaAberta->condutor() }} · {{ $saidaAberta->trecho() }}@if ($saidaAberta->equipamento) · levando {{ $saidaAberta->equipamento->name }}@endif</span>
                        @elseif (isset($noLocal[$v->id]))
                            <span class="mt-1 block text-xs text-sky-700">📍 em {{ $noLocal[$v->id]->destino }} desde {{ $noLocal[$v->id]->retorno_em->format('d/m H:i') }}</span>
                        @endif
                    </span>
                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $saidaAberta ? 'bg-amber-100 text-amber-700' : (isset($noLocal[$v->id]) ? 'bg-sky-100 text-sky-700' : 'bg-emerald-100 text-emerald-700') }}">{{ $saidaAberta ? 'EM TRÂNSITO' : (isset($noLocal[$v->id]) ? 'NO CLIENTE' : 'NA BASE') }}</span>
                </button>
            @empty
                <p class="rounded-xl bg-white p-4 text-sm text-zinc-600 border border-zinc-200 shadow-sm">Nenhum veículo cadastrado.</p>
            @endforelse
        @else
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <h2 class="text-lg font-extrabold leading-tight text-zinc-900">{{ $ativo->name }}</h2>
                <p class="mt-1 text-sm font-medium text-zinc-600">Placa: {{ $ativo->placa ?? '—' }} · Odômetro: {{ number_format((float) $ativo->odometro_atual, 0, ',', '.') }} km</p>
                @if (! $aberta && ($bloqueio = $ativo->bloqueioChecklistFrota()))
                    <p class="mt-2 rounded-lg bg-red-100 px-3 py-2 text-xs font-bold text-red-600">🚫 Bloqueado pelo checklist desde {{ $bloqueio->concluido_em?->format('d/m H:i') }}. Não pode sair até ser liberado.</p>
                @endif
            </div>

            @if ($aberta)
                <div class="rounded-xl bg-amber-100 p-4 text-sm text-zinc-800">
                    <p class="font-bold text-amber-700">Saiu em {{ $aberta->saida_em->format('d/m/Y H:i') }}</p>
                    <p class="mt-1">{{ $aberta->condutor() }} · {{ $finalidades[$aberta->finalidade] ?? $aberta->finalidade }}</p>
                    <p>Trecho: <strong>{{ $aberta->trecho() }}</strong></p>
                    @if ($aberta->percurso)<p>Percurso: {{ $aberta->percurso->nome }}</p>@endif
                    <p>Motivo: {{ $aberta->motivo }}</p>
                    @if ($aberta->equipamento)
                        <p>Equipamento levado: <strong>{{ $aberta->equipamento->name }}</strong>{{ $aberta->equipamento->patrimonio ? ' · PAT '.$aberta->equipamento->patrimonio : '' }}</p>
                    @endif
                    <p>Km na saída: {{ number_format($aberta->odometro_saida, 0, ',', '.') }}</p>
                </div>

                <form wire:submit="entrada" class="space-y-3 rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                    <p class="text-xs font-bold tracking-widest text-zinc-600">REGISTRAR ENTRADA</p>
                    @include('livewire.partials.saida-veiculo-campos-comuns', ['rotuloData' => 'DATA E HORA DA ENTRADA', 'rotuloKm' => 'ODÔMETRO NA ENTRADA (KM)'])
                    <label class="block text-xs font-bold tracking-wider text-zinc-600">OBSERVAÇÕES (AVARIAS, OCORRÊNCIAS)
                        <textarea wire:model="observacoes" rows="2" class="mt-1 w-full rounded-xl bg-zinc-100 px-3 py-2 text-base text-zinc-900 border border-zinc-300"></textarea>
                    </label>
                    <button type="submit" class="flex min-h-[3.25rem] w-full items-center justify-center rounded-xl bg-blue-600 text-sm font-bold text-white active:bg-blue-600">REGISTRAR ENTRADA</button>
                </form>
            @else
                <form wire:submit="saida" class="space-y-3 rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                    <p class="text-xs font-bold tracking-widest text-zinc-600">REGISTRAR SAÍDA</p>
                    <label class="block text-xs font-bold tracking-wider text-zinc-600">FINALIDADE
                        <select wire:model="finalidade" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                            @foreach ($finalidades as $k => $nome)
                                <option value="{{ $k }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-xs font-bold tracking-wider text-zinc-600">MOTORISTA
                        <select wire:model="motoristaId" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                            <option value="">Escolha o motorista</option>
                            @foreach ($motoristas as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if (count($motoristas) === 0)
                        <p class="text-xs text-amber-700">Nenhum motorista cadastrado. Cadastre em Logística → Frota → Motoristas.</p>
                    @endif
                    @if ($localAtual && $localAtual['tipo'] !== 'empresa')
                        <p class="rounded-xl bg-sky-100 px-3 py-2 text-xs font-bold text-sky-700">📍 Veículo está em: {{ $localAtual['rotulo'] }}</p>
                    @endif
                    @foreach ([['origem', 'SAINDO DE', 'origemTipo', 'origemClienteId', 'origemTexto'], ['destino', 'INDO PARA', 'destinoTipo', 'destinoClienteId', 'destino']] as [$prefixo, $rotuloTrecho, $propTipo, $propCliente, $propTexto])
                        <div class="rounded-xl bg-zinc-100 p-3">
                            <p class="text-xs font-bold tracking-wider text-zinc-600">{{ $rotuloTrecho }}</p>
                            <select wire:model.live="{{ $propTipo }}" class="mt-2 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                                <option value="empresa">Empresa</option>
                                <option value="cliente">Cliente</option>
                                <option value="outro">Outro local</option>
                            </select>
                            @if ($this->{$propTipo} === 'cliente')
                                <select wire:model.live="{{ $propCliente }}" class="mt-2 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                                    <option value="">Escolha o cliente</option>
                                    @foreach ($clientes as $idCliente => $nomeCliente)<option value="{{ $idCliente }}">{{ $nomeCliente }}</option>@endforeach
                                </select>
                            @elseif ($this->{$propTipo} === 'outro')
                                <input type="text" wire:model.live.debounce.400ms="{{ $propTexto }}" placeholder="Nome do local" class="mt-2 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                            @endif
                        </div>
                    @endforeach
                    @if ($percursosCompativeis->isNotEmpty())
                        <label class="block text-xs font-bold tracking-wider text-zinc-600">PERCURSO (RECEBE O KM DA VIAGEM)
                            <select wire:model="percursoId" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
                                <option value="">{{ $percursosCompativeis->count() === 1 ? 'Automático: '.$percursosCompativeis->first()->nome : 'Escolha o percurso' }}</option>
                                @foreach ($percursosCompativeis as $p)<option value="{{ $p->id }}">{{ $p->nome }}{{ $p->km_previsto ? ' · previsto '.rtrim(rtrim(number_format((float) $p->km_previsto, 1, ',', '.'), '0'), ',').' km' : '' }}</option>@endforeach
                            </select>
                        </label>
                    @endif
                    <label class="block text-xs font-bold tracking-wider text-zinc-600">MOTIVO
                        <textarea wire:model="motivo" rows="2" class="mt-1 w-full rounded-xl bg-zinc-100 px-3 py-2 text-base text-zinc-900 border border-zinc-300"></textarea>
                    </label>
                    <div class="rounded-xl bg-zinc-100 p-3">
                        <p class="text-xs font-bold tracking-wider text-zinc-600">EQUIPAMENTO QUE VAI NO VEÍCULO (SE FOR)</p>
                        @if ($equipamentoSelecionado)
                            <div class="mt-2 flex items-center justify-between gap-3 rounded-xl bg-white p-3 border border-zinc-200 shadow-sm">
                                <span>
                                    <span class="block text-sm font-bold text-zinc-900">{{ $equipamentoSelecionado->name }}</span>
                                    <span class="block text-xs text-zinc-600">PAT {{ $equipamentoSelecionado->patrimonio ?? '—' }}@if ($equipamentoSelecionado->placa) · Placa {{ $equipamentoSelecionado->placa }}@endif</span>
                                </span>
                                <button type="button" wire:click="limparEquipamento" class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-bold text-zinc-700">Remover</button>
                            </div>
                        @else
                            <input type="text" wire:model.live.debounce.400ms="buscaEquipamento" placeholder="Patrimônio, placa ou nome (deixe vazio se não levar)" class="mt-2 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 placeholder:text-zinc-400 border border-zinc-300">
                            @foreach ($equipamentos as $eq)
                                <button type="button" wire:click="escolherEquipamento('{{ $eq->id }}')" class="mt-2 block w-full rounded-xl bg-white p-3 text-left active:bg-zinc-100 border border-zinc-200 shadow-sm">
                                    <span class="block text-sm font-bold text-zinc-900">{{ $eq->name }}</span>
                                    <span class="block text-xs text-zinc-600">PAT {{ $eq->patrimonio ?? '—' }}@if ($eq->placa) · Placa {{ $eq->placa }}@endif</span>
                                </button>
                            @endforeach
                            @if (mb_strlen(trim($buscaEquipamento)) >= 2 && $equipamentos->isEmpty())
                                <p class="mt-2 text-xs text-amber-700">Nenhum equipamento encontrado.</p>
                            @endif
                        @endif
                    </div>
                    @include('livewire.partials.saida-veiculo-campos-comuns', ['rotuloData' => 'DATA E HORA DA SAÍDA', 'rotuloKm' => 'ODÔMETRO NA SAÍDA (KM)'])
                    <button type="submit" class="flex min-h-[3.25rem] w-full items-center justify-center rounded-xl bg-emerald-600 text-sm font-bold text-white active:bg-emerald-600">REGISTRAR SAÍDA</button>
                </form>
            @endif
        @endif
    </main>
</div>

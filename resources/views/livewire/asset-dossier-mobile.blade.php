<x-mobile.shell title="Dossiê do Ativo" :subtitle="$asset ? $asset->name : 'Busque o equipamento'" :nav="! $modoApp">
        @if (! $asset)
            {{-- Busca --}}
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-600">Buscar Ativo</h3>
                <p class="mt-1 text-[11px] text-zinc-600">Patrimônio, placa, nome, tag ou nº de série — não precisa ser exato.</p>

                <form wire:submit="search" class="mt-3 space-y-2">
                    <input
                        type="text"
                        wire:model="query"
                        placeholder="Ex: PAT-0001, ABC1D23, Guindaste..."
                        class="w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900 placeholder:text-zinc-400 focus:ring-2 focus:ring-blue-500"
                        autofocus
                    />
                    @error('query')
                        <p class="text-[11px] text-red-600">{{ $message }}</p>
                    @enderror

                    <button
                        type="submit"
                        class="min-h-[3rem] w-full rounded-xl bg-blue-600 text-sm font-bold text-white active:bg-blue-600"
                    >
                        Buscar
                    </button>
                </form>
            </div>

            @if (! empty($searchResults))
                <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-600">
                        {{ count($searchResults) }} encontrados — toque para abrir
                    </h3>
                    <div class="mt-3 space-y-2">
                        @foreach ($searchResults as $result)
                            <button
                                type="button"
                                wire:click="selectResult('{{ $result['id'] }}')"
                                class="min-h-[3rem] w-full rounded-xl border border-zinc-300 p-3 text-left active:bg-zinc-100"
                            >
                                <span class="block text-sm font-bold text-zinc-900">{{ $result['name'] }}</span>
                                <span class="block text-[11px] text-zinc-600">Patrimônio: {{ $result['patrimonio'] ?? '—' }} · Tag: {{ $result['tag'] ?? '—' }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            {{-- Cabeçalho do ativo --}}
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                {{-- Fotos do equipamento (até 3; a 1ª é a principal) --}}
                @php
                    $fotos = $asset->getMedia('fotos');
                @endphp
                @if ($fotos->isNotEmpty())
                    <div class="-mx-4 -mt-4 mb-3 overflow-hidden rounded-t-2xl">
                        <img src="{{ $fotos->first()->getUrl() }}" alt="Foto de {{ $asset->name }}" class="h-48 w-full object-cover" loading="lazy">
                        @if ($fotos->count() > 1)
                            <div class="flex gap-1 bg-zinc-50 p-1">
                                @foreach ($fotos->slice(1) as $foto)
                                    <img src="{{ $foto->hasGeneratedConversion('thumb') ? $foto->getUrl('thumb') : $foto->getUrl() }}" alt="" class="h-16 w-16 rounded object-cover" loading="lazy">
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
                <h2 class="text-xl font-extrabold leading-tight text-zinc-900">{{ $asset->name }}</h2>
                @if ($asset->isVehicle())
                    @php
                        $pendenciasFrota = app(\App\Services\Frota\PendenciasFrotaService::class)->doVeiculo($asset);
                    @endphp
                    @if ($pendenciasFrota->isNotEmpty())
                        <div class="mt-2 rounded-lg bg-amber-100 px-3 py-2">
                            <p class="text-xs font-bold text-amber-700">⚠ {{ $pendenciasFrota->count() }} pendência(s) da frota</p>
                            <ul class="mt-1 list-disc pl-4 text-xs text-zinc-700">
                                @foreach ($pendenciasFrota->take(5) as $pf)
                                    <li>{{ $pf['mensagem'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($bloqueioFrota = $asset->bloqueioChecklistFrota())
                        <p class="mt-2 rounded-lg bg-red-100 px-3 py-2 text-xs font-bold text-red-600">🚫 Veículo BLOQUEADO pelo checklist ({{ $bloqueioFrota->concluido_em?->format('d/m H:i') }}). Não pode sair até ser liberado.</p>
                    @endif
@unless ($modoApp)
                    <a href="{{ route('frota.checklist.mobile', ['assetId' => $asset->id]) }}" class="mt-3 flex min-h-[3rem] items-center justify-center rounded-xl bg-emerald-600 text-sm font-bold text-white active:bg-emerald-600">📋 CHECKLIST DE SAÍDA / RETORNO</a>
                    <a href="{{ route('frota.oleo.mobile', ['assetId' => $asset->id]) }}" class="mt-2 flex min-h-[3rem] items-center justify-center rounded-xl bg-zinc-100 text-sm font-bold text-zinc-900 active:bg-zinc-200">🛢️ ÓLEO (TROCA / REPOSIÇÃO)</a>
@endunless
                    @if ($modoApp)
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            @foreach ([['colab.logistica.saida', \App\Models\FrotaSaidaVeiculo::class, '🚚 Entrada / saída'], ['colab.logistica.abastecimento', \App\Models\FrotaAbastecimento::class, '⛽ Abastecer'], ['colab.logistica.km', \App\Models\FrotaSaidaVeiculo::class, '🔢 Marcar km'], ['colab.logistica.motorista', \App\Models\FrotaVinculoMotorista::class, '🧑‍✈️ Motorista']] as [$rotaLog, $modeloLog, $rotuloLog])
                                @can('viewAny', $modeloLog)
                                    <a href="{{ route($rotaLog, ['assetId' => $asset->id]) }}" class="flex min-h-[3rem] items-center justify-center rounded-xl bg-blue-600 text-xs font-bold text-white active:bg-blue-600">{{ $rotuloLog }}</a>
                                @endcan
                            @endforeach
                        </div>
                    @endif
                @endif
                <p class="mt-1 text-sm font-medium text-zinc-600">
                    PAT: {{ $asset->patrimonio ?? '—' }} · TAG: {{ $asset->tag ?? '—' }}
                </p>
                @php
                    $situacao = \App\Support\Colaborador\SituacaoOs::doAtivo($asset);
                @endphp
                <div class="mt-3 rounded-xl bg-zinc-100 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-zinc-600">Situação</span>
                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ \App\Support\Colaborador\SituacaoOs::classes($situacao['cor']) }}">{{ $situacao['rotulo'] }}</span>
                    </div>
                    @foreach ($situacao['abertas'] as $os)
                        @php $abrir = $modoApp ? route('colab.os', ['id' => $os['id']]) : null; @endphp
                        <div class="relative mt-2 rounded-lg border border-zinc-200 p-3 {{ $abrir ? 'active:bg-zinc-100' : '' }}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-bold text-zinc-900">OS {{ $os['numero'] }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ \App\Support\Colaborador\SituacaoOs::classes($os['situacao']['cor']) }}">{{ $os['situacao']['rotulo'] }}</span>
                            </div>
                            <p class="mt-1 text-xs text-zinc-600">Aberta em {{ $os['aberta_em']->format('d/m/Y') }} · <strong class="text-zinc-800">{{ $os['dias'] }} {{ $os['dias'] === 1 ? 'dia' : 'dias' }}</strong> em aberto</p>
                            <p class="text-xs text-zinc-600">Técnico: {{ $os['tecnico'] ?? 'não definido' }}</p>
                            @if ($abrir)<a href="{{ $abrir }}" class="mt-1 block text-[11px] font-bold text-blue-700 after:absolute after:inset-0">Ver dossiê da OS →</a>@endif
                        </div>
                    @endforeach
                    @if ($modoApp)
                        <a href="{{ route('colab.os.ativo', ['id' => $asset->id]) }}" class="mt-3 block text-xs font-bold text-blue-700">Ver todas as OS deste equipamento →</a>
                    @endif
                </div>
                <div class="mt-3 flex gap-2">
@unless ($modoApp)
                    <a
                        href="{{ route('assets.dossier.pdf', $asset) }}"
                        target="_blank"
                        class="flex min-h-[2.75rem] flex-1 items-center justify-center rounded-xl border border-zinc-300 text-xs font-bold text-zinc-800 active:bg-zinc-100"
                    >
                        Ver e imprimir
                    </a>
@endunless
                    <button
                        type="button"
                        wire:click="clear"
                        class="flex min-h-[2.75rem] flex-1 items-center justify-center rounded-xl border border-zinc-300 text-xs font-bold text-zinc-800 active:bg-zinc-100"
                    >
                        Nova Busca
                    </button>
                </div>
            </div>

            {{-- Dados gerais --}}
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-600">Dados Gerais</h3>
                <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    @if ($asset->placa)
                        <div><span class="block text-[11px] text-zinc-600">{{ $asset->is_vehicle ? 'Placa' : 'Placa do equipamento' }}</span><span class="font-semibold text-zinc-900">{{ $asset->placa }}</span></div>
                    @endif
                    <div><span class="block text-[11px] text-zinc-600">Categoria</span><span class="font-semibold text-zinc-900">{{ $asset->asset_category ?? '—' }}</span></div>
                    <div><span class="block text-[11px] text-zinc-600">Status</span><span class="font-semibold text-zinc-900">{{ ucfirst($asset->status ?? '—') }}</span></div>
                    <div><span class="block text-[11px] text-zinc-600">Especificação</span><span class="font-semibold text-zinc-900">{{ $asset->specification ?? '—' }}</span></div>
                    <div><span class="block text-[11px] text-zinc-600">Criticidade</span><span class="font-semibold text-zinc-900">{{ $asset->criticality_level ?? $asset->criticality ?? '—' }}</span></div>
                </div>
            </div>

            {{-- Cliente atual --}}
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-600">Cliente Atual</h3>
                <p class="mt-2 text-sm text-zinc-900">
                    {{ $asset->client?->name ?? 'Disponível — sem cliente vinculado no momento.' }}
                </p>
            </div>

            {{-- Contrato vigente --}}
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-600">Contrato Vigente</h3>
                @if ($this->currentContract)
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div><span class="block text-[11px] text-zinc-600">Nº Contrato</span><span class="font-semibold text-zinc-900">{{ $this->currentContract->contract_number }}</span></div>
                        <div><span class="block text-[11px] text-zinc-600">Cliente</span><span class="font-semibold text-zinc-900">{{ $this->currentContract->client?->name ?? '—' }}</span></div>
                        <div><span class="block text-[11px] text-zinc-600">Início</span><span class="font-semibold text-zinc-900">{{ optional($this->currentContract->start_date)->format('d/m/Y') ?? '—' }}</span></div>
                        <div><span class="block text-[11px] text-zinc-600">Valor Mensal</span><span class="font-semibold text-zinc-900">R$ {{ number_format((float) $this->currentContract->price, 2, ',', '.') }}</span></div>
                    </div>
                @else
                    <p class="mt-2 text-sm text-zinc-600">Nenhum contrato ativo para este ativo.</p>
                @endif
            </div>

            {{-- Horas trabalhadas (máquina) ou quilometragem (veículo) --}}
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                @if ($asset->is_vehicle)
                    <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-600">Quilometragem</h3>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div><span class="block text-[11px] text-zinc-600">Odômetro Atual</span><span class="font-semibold text-zinc-900">{{ number_format((float) $asset->odometro_atual, 0, ',', '.') }} km</span></div>
                        <div><span class="block text-[11px] text-zinc-600">Km de Aquisição</span><span class="font-semibold text-zinc-900">{{ number_format((float) $asset->horimetro_inicial, 0, ',', '.') }} km</span></div>
                        @if ($asset->carroceria_tipo || $asset->quantidade_eixos)
                            <div><span class="block text-[11px] text-zinc-600">Carroceria</span><span class="font-semibold text-zinc-900">{{ \App\Models\Asset::carroceriaLabels()[$asset->carroceria_tipo] ?? '—' }}{{ $asset->carroceria_detalhe ? ' · '.$asset->carroceria_detalhe : '' }}</span></div>
                            <div><span class="block text-[11px] text-zinc-600">Eixos</span><span class="font-semibold text-zinc-900">{{ $asset->quantidade_eixos ?? '—' }}</span></div>
                        @endif
                    </div>
                @else
                    <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-600">Horas Trabalhadas</h3>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div><span class="block text-[11px] text-zinc-600">Horímetro Atual</span><span class="font-semibold text-zinc-900">{{ number_format((float) $asset->horimetro_atual, 2, ',', '.') }} h</span></div>
                    </div>
                @endif
            </div>

            {{-- Link publico pro cliente locatario registrar o horimetro
                 ele mesmo, sem precisar de tecnico nem login -- so aparece
                 com o ativo locado. --}}
            @if ($this->hourMeterPublicLink)
                <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm" x-data="{ copied: false }">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-600">🔗 Link Público — Cliente Locatário</h3>
                    <p class="mt-1 text-[11px] text-zinc-600">
                        Compartilhe com o responsável designado da empresa que alugou o equipamento pra ele
                        registrar o horímetro sem precisar de conta no sistema. Fica identificado no relatório
                        pelo nome que a pessoa informar.
                    </p>
                    <button
                        type="button"
                        x-on:click="navigator.clipboard.writeText('{{ $this->hourMeterPublicLink }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="mt-3 flex min-h-[2.75rem] w-full items-center justify-center rounded-xl border border-zinc-300 text-xs font-bold text-zinc-800 active:bg-zinc-100"
                    >
                        <span x-show="!copied">Copiar Link</span>
                        <span x-show="copied" x-cloak class="text-emerald-700">✓ Copiado!</span>
                    </button>
                </div>
            @endif

            {{-- Registrar apontamento de horímetro --}}
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-600">✍️ Registrar Apontamento</h3>

                {{-- Última leitura salva: valor + data/hora + foto (se houve),
                     como evidencia/auditoria de quando e como foi registrada. --}}
                @if ($this->lastHorimeterReading)
                    <div class="mt-2 rounded-xl border border-zinc-200 bg-zinc-100 p-3">
                        <p class="text-[11px] text-zinc-600">Última leitura registrada</p>
                        <p class="mt-1 text-sm font-semibold text-zinc-900">
                            {{ number_format((float) $this->lastHorimeterReading->reading, 2, ',', '.') }}h
                            <span class="text-zinc-600">— {{ $this->lastHorimeterReading->recorded_at->format('d/m/Y \à\s H:i') }}</span>
                        </p>
                        @if ($this->lastHorimeterReading->photo_path)
                            <img
                                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($this->lastHorimeterReading->photo_path) }}"
                                alt="Foto da última leitura"
                                class="mt-2 h-24 w-full rounded-lg object-cover"
                            >
                        @else
                            <p class="mt-2 text-[11px] text-zinc-500">Sem foto anexada nesse apontamento.</p>
                        @endif
                    </div>
                @else
                    <p class="mt-1 text-[11px] text-zinc-600">Nenhum apontamento registrado ainda para este ativo.</p>
                @endif

                @if ($horimeterSaved)
                    <div class="mt-3 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700">
                        ✓ Apontamento registrado com sucesso
                    </div>
                @endif

                {{-- Data/hora que sera' gravada -- fixa no momento do render;
                     nao e' um relogio ao vivo (LivewireJS nao re-renderiza so'
                     pra isso), mas atualiza a cada interacao com a tela. --}}
                <p class="mt-3 text-[11px] font-semibold text-zinc-600">
                    🕐 Este apontamento sera' registrado em {{ now()->format('d/m/Y \à\s H:i') }}
                </p>

                <div class="mt-3 space-y-3">
                    <div>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model.live="horimeterReading"
                            placeholder="Leitura atual (horas)"
                            class="w-full rounded-xl border-0 bg-zinc-100 p-3 text-sm text-zinc-900 placeholder:text-zinc-400 focus:ring-2 focus:ring-blue-500"
                        />
                        @error('horimeterReading')
                            <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($this->horimeterNeedsResetConfirm)
                        <label class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3">
                            <input
                                type="checkbox"
                                wire:model="horimeterResetConfirmed"
                                class="mt-0.5 rounded border-zinc-300 bg-zinc-100 text-amber-500 focus:ring-amber-500"
                            >
                            <span class="text-[11px] font-semibold text-amber-700">
                                Leitura menor que a anterior, ou salto grande demais. Confirmo que este valor está correto (ex: reset do horímetro por troca de painel).
                            </span>
                        </label>
                    @endif

                    @if ($horimeterPhoto)
                        <div class="relative h-32 w-full">
                            <img src="{{ $horimeterPhoto->temporaryUrl() }}" alt="Foto do painel" class="h-32 w-full rounded-xl object-cover">
                            <button
                                type="button"
                                wire:click="$set('horimeterPhoto', null)"
                                class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white hover:bg-red-600"
                            >
                                ✕
                            </button>
                        </div>
                    @else
                        <label class="flex min-h-[3rem] cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-zinc-300 hover:border-zinc-300">
                            <input type="file" accept="image/*" capture="environment" wire:model="horimeterPhoto" class="hidden">
                            <span class="text-xs font-semibold text-zinc-600">📷 Foto do painel (opcional)</span>
                        </label>
                    @endif
                    <div wire:loading wire:target="horimeterPhoto" class="text-[11px] text-zinc-600">Enviando foto...</div>
                    @error('horimeterPhoto')
                        <p class="text-[11px] text-red-600">{{ $message }}</p>
                    @enderror

                    <textarea
                        wire:model="horimeterNotes"
                        rows="2"
                        placeholder="Observações (opcional)"
                        class="w-full rounded-xl border-0 bg-zinc-100 p-3 text-sm text-zinc-900 placeholder:text-zinc-400 focus:ring-2 focus:ring-blue-500"
                    ></textarea>

                    <button
                        type="button"
                        wire:click="saveHorimeterReading"
                        wire:loading.attr="disabled"
                        wire:target="saveHorimeterReading"
                        class="min-h-[3rem] w-full rounded-xl bg-blue-600 text-sm font-bold text-white active:bg-blue-600 disabled:opacity-50"
                    >
                        Salvar Apontamento
                    </button>
                </div>
            </div>

            {{-- Matriz ABC --}}
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-600">Matriz ABC</h3>
                @if ($asset->abcMatrix)
                    @php
                        $abcColor = match ($asset->abcMatrix->nivel) {
                            'A' => 'bg-red-100 text-red-600',
                            'B' => 'bg-amber-100 text-amber-700',
                            default => 'bg-emerald-100 text-emerald-700',
                        };
                    @endphp
                    <span class="mt-2 inline-block rounded-full px-3 py-1 text-xs font-bold {{ $abcColor }}">
                        Nível {{ $asset->abcMatrix->nivel }} — {{ $asset->abcMatrix->descricao }}
                    </span>
                @else
                    <p class="mt-2 text-sm text-zinc-600">Sem classificação ABC cadastrada.</p>
                @endif
            </div>

            {{-- Avarias recentes --}}
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-600">Avarias Recentes</h3>
                <div class="mt-2 space-y-2">
                    @forelse ($this->recentDamages as $damage)
                        <div class="border-t border-zinc-200 pt-2 text-sm first:border-t-0 first:pt-0">
                            <p class="font-semibold text-zinc-900">{{ ucfirst($damage->severity) }} — {{ $damage->description }}</p>
                            <p class="text-[11px] text-zinc-600">{{ $damage->created_at->format('d/m/Y H:i') }} · {{ $damage->status }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-600">Nenhuma avaria registrada.</p>
                    @endforelse
                </div>
            </div>

            {{-- OS abertas --}}
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-600">Ordens de Serviço Abertas</h3>
                <div class="mt-2 space-y-2">
                    @forelse ($this->openOrders as $order)
                        <div class="border-t border-zinc-200 pt-2 text-sm first:border-t-0 first:pt-0">
                            <p class="font-semibold text-zinc-900">OS {{ $order->os_number }} — {{ $order->status }}</p>
                            <p class="text-[11px] text-zinc-600">Técnico: {{ $order->technician?->name ?? 'Não atribuído' }}</p>
                            <p class="text-[11px] text-zinc-600">Problema: {{ $order->reportedProblem?->description ?? $order->description ?? '—' }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-600">Nenhuma OS em aberto.</p>
                    @endforelse
                </div>
            </div>
        @endif
</x-mobile.shell>

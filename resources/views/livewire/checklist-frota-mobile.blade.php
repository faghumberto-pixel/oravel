<div class="mx-auto flex min-h-screen max-w-md flex-col bg-zinc-50 text-zinc-900 md:h-full md:min-h-0 md:overflow-y-auto">
    <header class="mb-4 flex items-center justify-between rounded-b-3xl bg-gradient-to-r from-blue-600 to-blue-500 px-5 pb-5 pt-6 text-white shadow-md">
        <a href="{{ route('assets.dossier.mobile', ['assetId' => $ativo->id]) }}" class="text-xs font-bold tracking-widest text-blue-100">← VOLTAR</a>
        <h1 class="text-xs font-bold tracking-widest text-blue-100">CHECKLIST DA FROTA</h1>
    </header>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-32">
        {{-- Veículo --}}
        <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
            <h2 class="text-lg font-extrabold leading-tight text-zinc-900">{{ $ativo->name }}</h2>
            <p class="mt-1 text-sm font-medium text-zinc-600">Placa: {{ $ativo->placa ?? '—' }} · Odômetro: {{ number_format((float) $ativo->odometro_atual, 0, ',', '.') }} km</p>
        </div>

        @if ($this->checklistEnviado)
            @php
                $enviado = $this->checklistEnviado;
            @endphp
            @if ($enviado->situacao === \App\Models\FrotaChecklist::BLOQUEADO)
                <div class="rounded-xl bg-red-100 p-5">
                    <p class="text-lg font-extrabold text-red-600">🚫 VEÍCULO BLOQUEADO</p>
                    <p class="mt-2 text-sm text-zinc-800">Há item crítico com problema. <strong>O veículo não pode sair</strong> até um responsável liberar, com o motivo registrado.</p>
                </div>
            @elseif ($enviado->situacao === \App\Models\FrotaChecklist::ATENCAO)
                <div class="rounded-xl bg-amber-100 p-5">
                    <p class="text-lg font-extrabold text-amber-700">⚠️ Enviado, com pontos de atenção</p>
                    <p class="mt-2 text-sm text-zinc-800">O veículo pode seguir, mas os problemas ficaram registrados para a manutenção.</p>
                </div>
            @else
                <div class="rounded-xl bg-emerald-100 p-5">
                    <p class="text-lg font-extrabold text-emerald-700">✅ Checklist enviado</p>
                    <p class="mt-2 text-sm text-zinc-800">Tudo certo. Boa viagem!</p>
                </div>
            @endif
            @if ($enviado->novosProblemasNoRetorno()->isNotEmpty())
                <div class="rounded-xl bg-red-100 p-4">
                    <p class="text-sm font-bold text-red-600">Problemas novos em relação à saída:</p>
                    <ul class="mt-1 list-disc pl-5 text-sm text-zinc-800">
                        @foreach ($enviado->novosProblemasNoRetorno() as $novo)
                            <li>{{ $novo->descricao_registrada }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <a href="{{ route('assets.dossier.mobile', ['assetId' => $ativo->id]) }}" class="flex min-h-[3rem] items-center justify-center rounded-xl bg-blue-600 text-sm font-bold text-white">VOLTAR AO VEÍCULO</a>
        @else
            @if ($bloqueio = $ativo->bloqueioChecklistFrota())
                <div class="rounded-xl bg-red-100 p-4">
                    <p class="text-sm font-bold text-red-600">🚫 Este veículo está bloqueado desde {{ $bloqueio->concluido_em?->format('d/m H:i') }}.</p>
                    <p class="mt-1 text-xs text-zinc-700">Um responsável precisa liberá-lo (em Gestão de Frota → Checklists) antes da próxima saída.</p>
                </div>
            @endif

            @if ($completoVencido)
                <div class="rounded-xl bg-amber-100 p-3">
                    <p class="text-xs font-bold text-amber-700">Faz mais de {{ \App\Models\FrotaChecklist::DIAS_CHECKLIST_COMPLETO }} dias sem checklist COMPLETO neste veículo. Se puder, escolha o modelo completo abaixo.</p>
                </div>
            @endif

            {{-- Saída ou retorno --}}
            <div class="grid grid-cols-2 gap-2">
                @foreach (\App\Models\FrotaChecklist::tipoLabels() as $valor => $rotulo)
                    <button type="button" wire:click="$set('tipo', '{{ $valor }}')"
                            class="min-h-[3.25rem] rounded-xl text-sm font-bold {{ $tipo === $valor ? 'bg-blue-600 text-white' : 'bg-white text-zinc-700' }}">
                        {{ strtoupper($rotulo) }}
                    </button>
                @endforeach
            </div>

            <div class="space-y-3 rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <label class="block text-xs font-bold uppercase tracking-wide text-zinc-600">Modelo de checklist
                    <select wire:model.live="modeloId" class="mt-1 w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900">
                        @foreach ($modelos as $m)
                            <option value="{{ $m->id }}">{{ $m->nome }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-xs font-bold uppercase tracking-wide text-zinc-600">Motorista
                    <select wire:model="motoristaId" class="mt-1 w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900">
                        <option value="">— selecione —</option>
                        @foreach ($this->motoristas as $mot)
                            <option value="{{ $mot->id }}">{{ $mot->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-xs font-bold uppercase tracking-wide text-zinc-600">Odômetro (km)
                    <input type="text" inputmode="numeric" wire:model="odometro" class="mt-1 w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900">
                </label>
                @error('odometro') <p class="text-[11px] text-red-600">{{ $message }}</p> @enderror
                <label class="block text-xs font-bold uppercase tracking-wide text-zinc-600">Se o odômetro for MENOR que o anterior, explique
                    <input type="text" wire:model="justificativaOdometro" placeholder="Ex.: painel trocado" class="mt-1 w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900">
                </label>
                <label class="block text-xs font-bold uppercase tracking-wide text-zinc-600">Combustível
                    <select wire:model="combustivel" class="mt-1 w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900">
                        <option value="">—</option>
                        @foreach (\App\Models\FrotaChecklist::combustivelLabels() as $v => $r)
                            <option value="{{ $v }}">{{ $r }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            {{-- Itens --}}
            @foreach ($this->itens as $item)
                @php
                    $resultado = $resultados[$item->id] ?? null;
                @endphp
                <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm" wire:key="item-{{ $item->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-bold text-zinc-900">{{ $item->descricao }}</p>
                        @if ($item->gravidade === \App\Models\FrotaItemModeloChecklist::GRAVIDADE_CRITICA)
                            <span class="shrink-0 rounded bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-600">CRÍTICO</span>
                        @endif
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <button type="button" wire:click="marcar('{{ $item->id }}', 'ok')" class="min-h-[3rem] rounded-xl text-sm font-bold {{ $resultado === 'ok' ? 'bg-emerald-600 text-white' : 'bg-zinc-100 text-zinc-700' }}">OK</button>
                        <button type="button" wire:click="marcar('{{ $item->id }}', 'problema')" class="min-h-[3rem] rounded-xl text-sm font-bold {{ $resultado === 'problema' ? 'bg-red-500 text-white' : 'bg-zinc-100 text-zinc-700' }}">PROBLEMA</button>
                        <button type="button" wire:click="marcar('{{ $item->id }}', 'nao_se_aplica')" class="min-h-[3rem] rounded-xl text-xs font-bold {{ $resultado === 'nao_se_aplica' ? 'bg-zinc-300 text-zinc-900' : 'bg-zinc-100 text-zinc-600' }}">NÃO SE APLICA</button>
                    </div>
                    @if ($item->tipo_resposta === \App\Models\FrotaItemModeloChecklist::RESPOSTA_NUMERO && $resultado && $resultado !== 'nao_se_aplica')
                        <input type="text" inputmode="decimal" wire:model="valores.{{ $item->id }}" placeholder="Valor medido{{ $item->unidade ? ' ('.$item->unidade.')' : '' }}" class="mt-3 w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900">
                    @endif
                    @if ($resultado === 'problema')
                        <textarea wire:model="observacoesItens.{{ $item->id }}" rows="2" placeholder="Descreva o problema" class="mt-3 w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900"></textarea>
                        <label class="mt-2 block text-xs font-bold text-zinc-600">📷 Foto do problema{{ $item->exige_foto_se_problema ? ' (obrigatória)' : '' }}
                            <input type="file" accept="image/*" capture="environment" wire:model="fotosProblema.{{ $item->id }}" class="mt-1 block w-full text-xs text-zinc-700">
                        </label>
                    @endif
                </div>
            @endforeach
            @error('itens') <p class="rounded-xl bg-red-100 p-3 text-sm text-red-600">{{ $message }}</p> @enderror

            {{-- Fotos das laterais --}}
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <p class="text-sm font-bold text-zinc-900">Fotos do veículo (as 4 são obrigatórias)</p>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    @foreach (['fotoFrente' => 'Frente', 'fotoTraseira' => 'Traseira', 'fotoEsquerda' => 'Lado esquerdo', 'fotoDireita' => 'Lado direito'] as $campo => $rotulo)
                        <label class="block text-xs font-bold text-zinc-600">📷 {{ $rotulo }}
                            <input type="file" accept="image/*" capture="environment" wire:model="{{ $campo }}" class="mt-1 block w-full text-[11px] text-zinc-700">
                            @if ($this->{$campo}) <span class="text-emerald-700">✔ foto pronta</span> @endif
                            @error($campo) <span class="block text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <label class="block text-xs font-bold uppercase tracking-wide text-zinc-600">Observações (opcional)
                    <textarea wire:model="observacoes" rows="2" class="mt-1 w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900"></textarea>
                </label>
            </div>

            {{-- Assinatura --}}
            <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                <p class="text-sm font-bold text-zinc-900">Assinatura do motorista</p>
                <div
                    class="mt-3"
                    x-data="{
                        pad: null,
                        isEmpty: true,
                        feita: false,
                        initPad() {
                            this.pad = new SignaturePad(this.$refs.canvas, { penColor: '#fafafa', backgroundColor: '#27272a' });
                            this.pad.addEventListener('endStroke', () => { this.isEmpty = this.pad.isEmpty(); });
                            const canvas = this.$refs.canvas;
                            const ratio = Math.max(window.devicePixelRatio || 1, 1);
                            canvas.width = canvas.offsetWidth * ratio;
                            canvas.height = canvas.offsetHeight * ratio;
                            canvas.getContext('2d').scale(ratio, ratio);
                            this.pad.clear();
                        },
                        clear() { this.pad.clear(); this.isEmpty = true; this.feita = false; },
                        confirmar() { if (this.pad.isEmpty()) return; $wire.salvarAssinatura(this.pad.toDataURL('image/png')); this.feita = true; },
                    }"
                    x-init="initPad()"
                    wire:ignore
                >
                    <canvas x-ref="canvas" class="h-36 w-full rounded-xl bg-zinc-100"></canvas>
                    <div class="mt-2 flex gap-2">
                        <button type="button" x-on:click="clear()" class="min-h-[2.75rem] flex-1 rounded-xl border border-zinc-300 text-xs font-bold text-zinc-700">LIMPAR</button>
                        <button type="button" x-on:click="confirmar()" x-bind:disabled="isEmpty" class="min-h-[2.75rem] flex-1 rounded-xl bg-emerald-600 text-xs font-bold text-white disabled:bg-zinc-100 disabled:text-zinc-500">CONFIRMAR ASSINATURA</button>
                    </div>
                    <p x-show="feita" class="mt-1 text-xs font-bold text-emerald-700">✔ Assinatura confirmada</p>
                </div>
                @error('assinatura') <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif
    </main>

    @unless ($this->checklistEnviado)
        <footer class="fixed inset-x-0 bottom-0 mx-auto flex max-w-md gap-3 border-t border-zinc-200 bg-white px-5 py-4">
            <button type="button" wire:click="enviar" wire:loading.attr="disabled" class="min-h-[3.25rem] flex-1 rounded-xl bg-blue-600 text-sm font-bold text-white active:bg-blue-600 disabled:opacity-60">
                <span wire:loading.remove wire:target="enviar">ENVIAR CHECKLIST</span>
                <span wire:loading wire:target="enviar">Enviando...</span>
            </button>
        </footer>
    @endunless
</div>

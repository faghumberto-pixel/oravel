<div class="mx-auto flex min-h-screen max-w-md flex-col md:h-full md:min-h-0 md:overflow-y-auto">
    <header class="flex items-center justify-between px-5 pb-2 pt-6">
        <a href="{{ route('assets.dossier.mobile', ['assetId' => $ativo->id]) }}" class="text-xs font-bold tracking-widest text-zinc-400">← VOLTAR</a>
        <h1 class="text-xs font-bold tracking-widest text-zinc-400">CHECKLIST DA FROTA</h1>
    </header>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-32">
        {{-- Veículo --}}
        <div class="rounded-2xl bg-zinc-900 p-4">
            <h2 class="text-lg font-extrabold leading-tight text-white">{{ $ativo->name }}</h2>
            <p class="mt-1 text-sm font-medium text-zinc-400">Placa: {{ $ativo->placa ?? '—' }} · Odômetro: {{ number_format((float) $ativo->odometro_atual, 0, ',', '.') }} km</p>
        </div>

        @if ($this->checklistEnviado)
            @php
                $enviado = $this->checklistEnviado;
            @endphp
            @if ($enviado->situacao === \App\Models\FrotaChecklist::BLOQUEADO)
                <div class="rounded-2xl bg-red-500/15 p-5">
                    <p class="text-lg font-extrabold text-red-400">🚫 VEÍCULO BLOQUEADO</p>
                    <p class="mt-2 text-sm text-zinc-200">Há item crítico com problema. <strong>O veículo não pode sair</strong> até um responsável liberar, com o motivo registrado.</p>
                </div>
            @elseif ($enviado->situacao === \App\Models\FrotaChecklist::ATENCAO)
                <div class="rounded-2xl bg-amber-500/15 p-5">
                    <p class="text-lg font-extrabold text-amber-400">⚠️ Enviado, com pontos de atenção</p>
                    <p class="mt-2 text-sm text-zinc-200">O veículo pode seguir, mas os problemas ficaram registrados para a manutenção.</p>
                </div>
            @else
                <div class="rounded-2xl bg-emerald-500/15 p-5">
                    <p class="text-lg font-extrabold text-emerald-400">✅ Checklist enviado</p>
                    <p class="mt-2 text-sm text-zinc-200">Tudo certo. Boa viagem!</p>
                </div>
            @endif
            @if ($enviado->novosProblemasNoRetorno()->isNotEmpty())
                <div class="rounded-2xl bg-red-500/10 p-4">
                    <p class="text-sm font-bold text-red-400">Problemas novos em relação à saída:</p>
                    <ul class="mt-1 list-disc pl-5 text-sm text-zinc-200">
                        @foreach ($enviado->novosProblemasNoRetorno() as $novo)
                            <li>{{ $novo->descricao_registrada }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <a href="{{ route('assets.dossier.mobile', ['assetId' => $ativo->id]) }}" class="flex min-h-[3rem] items-center justify-center rounded-xl bg-blue-500 text-sm font-bold text-white">VOLTAR AO VEÍCULO</a>
        @else
            @if ($bloqueio = $ativo->bloqueioChecklistFrota())
                <div class="rounded-2xl bg-red-500/15 p-4">
                    <p class="text-sm font-bold text-red-400">🚫 Este veículo está bloqueado desde {{ $bloqueio->concluido_em?->format('d/m H:i') }}.</p>
                    <p class="mt-1 text-xs text-zinc-300">Um responsável precisa liberá-lo (em Gestão de Frota → Checklists) antes da próxima saída.</p>
                </div>
            @endif

            @if ($completoVencido)
                <div class="rounded-2xl bg-amber-500/10 p-3">
                    <p class="text-xs font-bold text-amber-400">Faz mais de {{ \App\Models\FrotaChecklist::DIAS_CHECKLIST_COMPLETO }} dias sem checklist COMPLETO neste veículo. Se puder, escolha o modelo completo abaixo.</p>
                </div>
            @endif

            {{-- Saída ou retorno --}}
            <div class="grid grid-cols-2 gap-2">
                @foreach (\App\Models\FrotaChecklist::tipoLabels() as $valor => $rotulo)
                    <button type="button" wire:click="$set('tipo', '{{ $valor }}')"
                            class="min-h-[3.25rem] rounded-xl text-sm font-bold {{ $tipo === $valor ? 'bg-blue-500 text-white' : 'bg-zinc-900 text-zinc-300' }}">
                        {{ strtoupper($rotulo) }}
                    </button>
                @endforeach
            </div>

            <div class="space-y-3 rounded-2xl bg-zinc-900 p-4">
                <label class="block text-xs font-bold uppercase tracking-wide text-zinc-400">Modelo de checklist
                    <select wire:model.live="modeloId" class="mt-1 w-full rounded-xl border-0 bg-zinc-800 p-3 text-base text-zinc-100">
                        @foreach ($modelos as $m)
                            <option value="{{ $m->id }}">{{ $m->nome }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-xs font-bold uppercase tracking-wide text-zinc-400">Motorista
                    <select wire:model="motoristaId" class="mt-1 w-full rounded-xl border-0 bg-zinc-800 p-3 text-base text-zinc-100">
                        <option value="">— selecione —</option>
                        @foreach ($this->motoristas as $mot)
                            <option value="{{ $mot->id }}">{{ $mot->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-xs font-bold uppercase tracking-wide text-zinc-400">Odômetro (km)
                    <input type="text" inputmode="numeric" wire:model="odometro" class="mt-1 w-full rounded-xl border-0 bg-zinc-800 p-3 text-base text-zinc-100">
                </label>
                @error('odometro') <p class="text-[11px] text-red-400">{{ $message }}</p> @enderror
                <label class="block text-xs font-bold uppercase tracking-wide text-zinc-400">Se o odômetro for MENOR que o anterior, explique
                    <input type="text" wire:model="justificativaOdometro" placeholder="Ex.: painel trocado" class="mt-1 w-full rounded-xl border-0 bg-zinc-800 p-3 text-base text-zinc-100">
                </label>
                <label class="block text-xs font-bold uppercase tracking-wide text-zinc-400">Combustível
                    <select wire:model="combustivel" class="mt-1 w-full rounded-xl border-0 bg-zinc-800 p-3 text-base text-zinc-100">
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
                <div class="rounded-2xl bg-zinc-900 p-4" wire:key="item-{{ $item->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-bold text-white">{{ $item->descricao }}</p>
                        @if ($item->gravidade === \App\Models\FrotaItemModeloChecklist::GRAVIDADE_CRITICA)
                            <span class="shrink-0 rounded bg-red-500/20 px-2 py-0.5 text-[10px] font-bold text-red-400">CRÍTICO</span>
                        @endif
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <button type="button" wire:click="marcar('{{ $item->id }}', 'ok')" class="min-h-[3rem] rounded-xl text-sm font-bold {{ $resultado === 'ok' ? 'bg-emerald-500 text-zinc-950' : 'bg-zinc-800 text-zinc-300' }}">OK</button>
                        <button type="button" wire:click="marcar('{{ $item->id }}', 'problema')" class="min-h-[3rem] rounded-xl text-sm font-bold {{ $resultado === 'problema' ? 'bg-red-500 text-white' : 'bg-zinc-800 text-zinc-300' }}">PROBLEMA</button>
                        <button type="button" wire:click="marcar('{{ $item->id }}', 'nao_se_aplica')" class="min-h-[3rem] rounded-xl text-xs font-bold {{ $resultado === 'nao_se_aplica' ? 'bg-zinc-600 text-white' : 'bg-zinc-800 text-zinc-400' }}">NÃO SE APLICA</button>
                    </div>
                    @if ($item->tipo_resposta === \App\Models\FrotaItemModeloChecklist::RESPOSTA_NUMERO && $resultado && $resultado !== 'nao_se_aplica')
                        <input type="text" inputmode="decimal" wire:model="valores.{{ $item->id }}" placeholder="Valor medido{{ $item->unidade ? ' ('.$item->unidade.')' : '' }}" class="mt-3 w-full rounded-xl border-0 bg-zinc-800 p-3 text-base text-zinc-100">
                    @endif
                    @if ($resultado === 'problema')
                        <textarea wire:model="observacoesItens.{{ $item->id }}" rows="2" placeholder="Descreva o problema" class="mt-3 w-full rounded-xl border-0 bg-zinc-800 p-3 text-base text-zinc-100"></textarea>
                        <label class="mt-2 block text-xs font-bold text-zinc-400">📷 Foto do problema{{ $item->exige_foto_se_problema ? ' (obrigatória)' : '' }}
                            <input type="file" accept="image/*" capture="environment" wire:model="fotosProblema.{{ $item->id }}" class="mt-1 block w-full text-xs text-zinc-300">
                        </label>
                    @endif
                </div>
            @endforeach
            @error('itens') <p class="rounded-xl bg-red-500/10 p-3 text-sm text-red-400">{{ $message }}</p> @enderror

            {{-- Fotos das laterais --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <p class="text-sm font-bold text-white">Fotos do veículo (as 4 são obrigatórias)</p>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    @foreach (['fotoFrente' => 'Frente', 'fotoTraseira' => 'Traseira', 'fotoEsquerda' => 'Lado esquerdo', 'fotoDireita' => 'Lado direito'] as $campo => $rotulo)
                        <label class="block text-xs font-bold text-zinc-400">📷 {{ $rotulo }}
                            <input type="file" accept="image/*" capture="environment" wire:model="{{ $campo }}" class="mt-1 block w-full text-[11px] text-zinc-300">
                            @if ($this->{$campo}) <span class="text-emerald-400">✔ foto pronta</span> @endif
                            @error($campo) <span class="block text-[11px] text-red-400">{{ $message }}</span> @enderror
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl bg-zinc-900 p-4">
                <label class="block text-xs font-bold uppercase tracking-wide text-zinc-400">Observações (opcional)
                    <textarea wire:model="observacoes" rows="2" class="mt-1 w-full rounded-xl border-0 bg-zinc-800 p-3 text-base text-zinc-100"></textarea>
                </label>
            </div>

            {{-- Assinatura --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <p class="text-sm font-bold text-white">Assinatura do motorista</p>
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
                    <canvas x-ref="canvas" class="h-36 w-full rounded-xl bg-zinc-800"></canvas>
                    <div class="mt-2 flex gap-2">
                        <button type="button" x-on:click="clear()" class="min-h-[2.75rem] flex-1 rounded-xl border border-zinc-700 text-xs font-bold text-zinc-300">LIMPAR</button>
                        <button type="button" x-on:click="confirmar()" x-bind:disabled="isEmpty" class="min-h-[2.75rem] flex-1 rounded-xl bg-emerald-500 text-xs font-bold text-zinc-950 disabled:bg-zinc-800 disabled:text-zinc-600">CONFIRMAR ASSINATURA</button>
                    </div>
                    <p x-show="feita" class="mt-1 text-xs font-bold text-emerald-400">✔ Assinatura confirmada</p>
                </div>
                @error('assinatura') <p class="mt-1 text-[11px] text-red-400">{{ $message }}</p> @enderror
            </div>
        @endif
    </main>

    @unless ($this->checklistEnviado)
        <footer class="fixed inset-x-0 bottom-0 mx-auto flex max-w-md gap-3 border-t border-zinc-800 bg-zinc-950 px-5 py-4">
            <button type="button" wire:click="enviar" wire:loading.attr="disabled" class="min-h-[3.25rem] flex-1 rounded-xl bg-blue-500 text-sm font-bold text-white active:bg-blue-600 disabled:opacity-60">
                <span wire:loading.remove wire:target="enviar">ENVIAR CHECKLIST</span>
                <span wire:loading wire:target="enviar">Enviando...</span>
            </button>
        </footer>
    @endunless
</div>

<div class="mx-auto flex min-h-screen max-w-md flex-col bg-zinc-50 text-zinc-900 md:h-full md:min-h-0 md:overflow-y-auto">
    <header class="mb-4 flex items-center justify-between rounded-b-3xl bg-gradient-to-r from-blue-600 to-blue-500 px-5 pb-5 pt-6 text-white shadow-md">
        <a href="{{ route('assets.dossier.mobile', ['assetId' => $ativo->id]) }}" class="text-xs font-bold tracking-widest text-blue-100">← VOLTAR</a>
        <h1 class="text-xs font-bold tracking-widest text-blue-100">CHECKLIST DO EQUIPAMENTO</h1>
    </header>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-32">
        {{-- Equipamento --}}
        <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
            <h2 class="text-lg font-extrabold leading-tight text-zinc-900">{{ $ativo->name }}</h2>
            <p class="mt-1 text-sm font-medium text-zinc-600">{{ $ativo->patrimonio ? 'Patrimônio: '.$ativo->patrimonio : '' }}{{ $ativo->category?->name ? ' · '.$ativo->category->name : '' }}</p>
        </div>

        @if ($this->execucaoEnviada)
            @php
                $enviada = $this->execucaoEnviada;
                $pendencias = $enviada->respostas->where('resposta', 'nao_conforme')->where('critico', false);
                $criticos = $enviada->respostas->where('resposta', 'nao_conforme')->where('critico', true);
            @endphp
            @if ($enviada->status === \App\Models\CkExecution::STATUS_REPROVADO)
                <div class="rounded-xl bg-red-100 p-5">
                    <p class="text-lg font-extrabold text-red-600">🚫 CHECKLIST REPROVADO</p>
                    <p class="mt-2 text-sm text-zinc-800">Há item crítico não conforme. <strong>O equipamento não deve operar</strong> até a correção.</p>
                    <ul class="mt-2 list-disc pl-5 text-sm text-zinc-800">
                        @foreach ($criticos as $c)
                            <li>{{ $c->item_descricao }}</li>
                        @endforeach
                    </ul>
                </div>
                @if ($this->osDaExecucao)
                    <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                        <p class="text-sm font-bold text-zinc-900">OS corretiva aberta{{ $this->osDaExecucao->os_number ? ' · nº '.$this->osDaExecucao->os_number : '' }}</p>
                        <p class="mt-1 text-xs text-zinc-600">Já com os itens reprovados e as fotos.</p>
                    </div>
                @elseif ($this->podeAbrirOs())
                    <button type="button" wire:click="abrirOs" wire:loading.attr="disabled" class="min-h-[3.25rem] w-full rounded-xl bg-red-500 text-sm font-bold text-white active:bg-red-600 disabled:opacity-60">
                        <span wire:loading.remove wire:target="abrirOs">ABRIR OS CORRETIVA AGORA</span>
                        <span wire:loading wire:target="abrirOs">Abrindo...</span>
                    </button>
                    @error('os') <p class="rounded-xl bg-red-100 p-3 text-sm text-red-600">{{ $message }}</p> @enderror
                @else
                    <p class="rounded-xl bg-amber-100 p-3 text-sm text-zinc-800">Avise o responsável pela manutenção para abrir a OS corretiva.</p>
                @endif
            @elseif ($enviada->status === \App\Models\CkExecution::STATUS_APROVADO_COM_RESSALVA)
                <div class="rounded-xl bg-amber-100 p-5">
                    <p class="text-lg font-extrabold text-amber-700">⚠️ Aprovado, com ressalvas</p>
                    <p class="mt-2 text-sm text-zinc-800">O equipamento pode seguir, mas os pontos abaixo ficaram registrados como pendência para a manutenção.</p>
                </div>
            @else
                <div class="rounded-xl bg-emerald-100 p-5">
                    <p class="text-lg font-extrabold text-emerald-700">✅ Checklist aprovado</p>
                    <p class="mt-2 text-sm text-zinc-800">Tudo conforme. Bom trabalho!</p>
                </div>
            @endif
            @if ($pendencias->isNotEmpty())
                <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                    <p class="text-sm font-bold text-zinc-900">Pendências registradas</p>
                    <ul class="mt-1 list-disc pl-5 text-sm text-zinc-800">
                        @foreach ($pendencias as $p)
                            <li>{{ $p->item_descricao }}@if ($p->observacao) <span class="text-zinc-600">— {{ $p->observacao }}</span>@endif</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <a href="{{ route('assets.dossier.mobile', ['assetId' => $ativo->id]) }}" class="flex min-h-[3rem] items-center justify-center rounded-xl bg-zinc-100 text-sm font-bold text-zinc-900 active:bg-zinc-200">VOLTAR AO EQUIPAMENTO</a>
        @elseif ($this->templates->isEmpty())
            <div class="rounded-xl bg-amber-100 p-4">
                <p class="text-sm font-bold text-amber-700">Nenhum checklist se aplica a este equipamento.</p>
                <p class="mt-1 text-xs text-zinc-800">Peça ao responsável para ligar um checklist ao grupo do equipamento ou ao grupo de checklist dele.</p>
            </div>
        @else
            {{-- Escolha do checklist (so quando ha mais de um) --}}
            @if ($this->templates->count() > 1)
                <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                    <label class="text-xs font-bold text-zinc-600">Qual checklist?</label>
                    <select wire:model.live="templateId" class="mt-1 w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900">
                        <option value="">Escolha…</option>
                        @foreach ($this->templates as $t)
                            <option value="{{ $t->id }}">{{ $t->nome }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($this->template)
                <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                    <p class="text-sm font-bold text-zinc-900">{{ $this->template->nome }}</p>
                    <label class="mt-3 block text-xs font-bold text-zinc-600">Horímetro ou km agora (opcional)
                        <input type="text" inputmode="decimal" wire:model="leitura" placeholder="Ex.: 1250" class="mt-1 w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900">
                    </label>
                    @error('leitura') <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Itens, por seção --}}
                @php $secaoAtual = null; @endphp
                @foreach ($this->itens as $item)
                    @php
                        $resposta = $respostas[$item->id] ?? null;
                        $naoConforme = $resposta === 'nao_conforme';
                    @endphp
                    @if ($secaoAtual !== $item->section_id)
                        @php $secaoAtual = $item->section_id; @endphp
                        <h3 class="px-1 pt-2 text-xs font-bold uppercase tracking-widest text-zinc-500">{{ $item->secao?->titulo }}</h3>
                    @endif
                    <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm" wire:key="ck-item-{{ $item->id }}">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-bold text-zinc-900">{{ $item->descricao }}</p>
                            @if ($item->critico)
                                <span class="shrink-0 rounded bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-600">CRÍTICO</span>
                            @endif
                        </div>
                        @if ($item->referencia_manual)
                            <p class="mt-1 text-[11px] text-zinc-500">Ref.: {{ $item->referencia_manual }}</p>
                        @endif

                        @if ($item->tipo_resposta === 'conforme_nao_conforme_na')
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <button type="button" wire:click="marcar('{{ $item->id }}', 'conforme')" class="min-h-[3rem] rounded-xl text-sm font-bold {{ $resposta === 'conforme' ? 'bg-emerald-600 text-white' : 'bg-zinc-100 text-zinc-800' }}">OK</button>
                                <button type="button" wire:click="marcar('{{ $item->id }}', 'nao_conforme')" class="min-h-[3rem] rounded-xl text-sm font-bold {{ $naoConforme ? 'bg-red-500 text-white' : 'bg-zinc-100 text-zinc-800' }}">PROBLEMA</button>
                                <button type="button" wire:click="marcar('{{ $item->id }}', 'na')" class="min-h-[3rem] rounded-xl text-xs font-bold {{ $resposta === 'na' ? 'bg-zinc-300 text-zinc-900' : 'bg-zinc-100 text-zinc-800' }}">NÃO SE APLICA</button>
                            </div>
                        @elseif ($item->tipo_resposta === 'numerico')
                            <input type="text" inputmode="decimal" wire:model="valores.{{ $item->id }}" placeholder="Valor medido{{ $item->unidade ? ' ('.$item->unidade.')' : '' }}" class="mt-3 w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900">
                        @elseif ($item->tipo_resposta === 'selecao')
                            <select wire:model="valores.{{ $item->id }}" class="mt-3 w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900">
                                <option value="">Escolha…</option>
                                @foreach ((array) $item->opcoes as $opcao)
                                    <option value="{{ $opcao }}">{{ $opcao }}</option>
                                @endforeach
                            </select>
                        @elseif ($item->tipo_resposta === 'texto')
                            <div class="mt-3">
                                <textarea wire:model="valores.{{ $item->id }}" rows="2" placeholder="Escreva aqui" class="w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900"></textarea>
                                <div class="mt-1">{!! \App\Support\FormHelpers::voiceButton() !!}</div>
                            </div>
                        @elseif ($item->tipo_resposta === 'foto')
                            <label class="mt-3 block text-xs font-bold text-zinc-600">📷 Foto
                                <input type="file" accept="image/*" capture="environment" wire:model="fotosItens.{{ $item->id }}" class="mt-1 block w-full text-xs text-zinc-700">
                            </label>
                        @endif

                        @if ($naoConforme)
                            <div class="mt-3">
                                <textarea wire:model="observacoesItens.{{ $item->id }}" rows="2" placeholder="Descreva o problema" class="w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900"></textarea>
                                <div class="mt-1">{!! \App\Support\FormHelpers::voiceButton() !!}</div>
                            </div>
                            <label class="mt-2 block text-xs font-bold text-zinc-600">📷 Foto do problema{{ $item->exige_foto_se_nao_conforme ? ' (obrigatória)' : '' }}
                                <input type="file" accept="image/*" capture="environment" wire:model="fotosItens.{{ $item->id }}" class="mt-1 block w-full text-xs text-zinc-700">
                            </label>
                        @endif
                    </div>
                @endforeach
                @error('itens') <p class="rounded-xl bg-red-100 p-3 text-sm text-red-600">{{ $message }}</p> @enderror
                @error('fotosItens.*') <p class="rounded-xl bg-red-100 p-3 text-sm text-red-600">{{ $message }}</p> @enderror

                {{-- Observações finais, com voz --}}
                <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                    <p class="text-sm font-bold text-zinc-900">Observações</p>
                    <div class="mt-2">
                        <textarea wire:model="observacoes" rows="3" placeholder="Algo mais que a manutenção precisa saber" class="w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900"></textarea>
                        <div class="mt-1">{!! \App\Support\FormHelpers::voiceButton() !!}</div>
                    </div>
                </div>

                {{-- Assinatura --}}
                <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                    <p class="text-sm font-bold text-zinc-900">Assinatura de quem fez o checklist</p>
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
                            <button type="button" x-on:click="confirmar()" x-bind:disabled="isEmpty" class="min-h-[2.75rem] flex-1 rounded-xl bg-emerald-600 text-xs font-bold text-white disabled:bg-zinc-300">CONFIRMAR ASSINATURA</button>
                        </div>
                        <p x-show="feita" class="mt-1 text-xs font-bold text-emerald-700">✔ Assinatura confirmada</p>
                    </div>
                    @error('assinatura') <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
        @endif
    </main>

    @if (! $this->execucaoEnviada && $this->template)
        <footer class="fixed inset-x-0 bottom-0 mx-auto flex max-w-md gap-3 border-t border-zinc-200 bg-white px-5 py-4">
            <button type="button" wire:click="enviar" wire:loading.attr="disabled" class="min-h-[3.25rem] flex-1 rounded-xl bg-blue-600 text-sm font-bold text-white active:bg-blue-600 disabled:opacity-60">
                <span wire:loading.remove wire:target="enviar">ENVIAR CHECKLIST</span>
                <span wire:loading wire:target="enviar">Enviando...</span>
            </button>
        </footer>
    @endif
</div>

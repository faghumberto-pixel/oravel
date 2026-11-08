<div class="ckd" id="ckd">
    <style>
        .ckd { max-width: 1100px; margin: 0 auto; padding: 24px 20px 90px; color: #0f172a; font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; font-size: 14px; line-height: 1.45; }
        .ckd * { box-sizing: border-box; }
        .ckd h1 { margin: 0; font-size: 22px; }
        .ckd .topo { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
        .ckd .sub { color: #475569; margin-top: 2px; }
        .ckd a.voltar { color: #1d4ed8; text-decoration: none; font-weight: 600; }
        .ckd .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 16px; }
        .ckd .card h2 { margin: 0 0 10px; font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: #475569; }
        .ckd .linha { display: flex; gap: 16px; flex-wrap: wrap; align-items: end; }
        .ckd label.campo { display: flex; flex-direction: column; gap: 4px; font-size: 12px; font-weight: 700; color: #475569; }
        .ckd input[type=text], .ckd select, .ckd textarea { border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; font: inherit; color: #0f172a; background: #fff; min-width: 0; }
        .ckd textarea { width: 100%; resize: vertical; }
        .ckd table { width: 100%; border-collapse: collapse; }
        .ckd th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #475569; padding: 6px 8px; border-bottom: 2px solid #e2e8f0; }
        .ckd td { padding: 10px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .ckd td.n { width: 34px; color: #64748b; font-weight: 700; }
        .ckd td.resp { width: 330px; }
        .ckd .cr { margin-left: 8px; padding: 0 6px; border: 1px solid #b91c1c; border-radius: 4px; color: #b91c1c; font-size: 10px; font-weight: 800; }
        .ckd .ref { display: block; font-size: 11px; color: #64748b; margin-top: 2px; }
        .ckd .btns { display: flex; gap: 6px; }
        .ckd .b { border: 1px solid #cbd5e1; background: #f8fafc; color: #0f172a; border-radius: 8px; padding: 8px 12px; font-weight: 700; cursor: pointer; }
        .ckd .b.ok.on { background: #059669; border-color: #059669; color: #fff; }
        .ckd .b.nc.on { background: #ef4444; border-color: #ef4444; color: #fff; }
        .ckd .b.na.on { background: #cbd5e1; border-color: #94a3b8; }
        .ckd tr.det td { background: #fef2f2; border-bottom: 1px solid #fecaca; }
        .ckd .secao td { background: #f1f5f9; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; font-size: 12px; color: #334155; }
        .ckd .enviar { background: #2563eb; color: #fff; border: 0; border-radius: 10px; padding: 12px 28px; font-size: 15px; font-weight: 800; cursor: pointer; }
        .ckd .enviar[disabled] { opacity: .6; }
        .ckd .erro { background: #fee2e2; color: #b91c1c; border-radius: 10px; padding: 10px 14px; margin-bottom: 12px; font-weight: 600; }
        .ckd .aviso { padding: 14px 18px; border-radius: 12px; margin-bottom: 14px; }
        .ckd .aviso.v { background: #dcfce7; } .ckd .aviso.a { background: #fef3c7; } .ckd .aviso.r { background: #fee2e2; }
        .ckd .aviso strong { font-size: 17px; display: block; margin-bottom: 4px; }
        .ckd canvas { width: 100%; height: 160px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; display: block; }
        .ckd .rodape { display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
        .ckd .progresso { color: #475569; font-weight: 600; }
        .ckd .peq { font-size: 12px; color: #475569; }
        .ckd a.botao { display: inline-block; background: #e2e8f0; color: #0f172a; border-radius: 10px; padding: 10px 18px; text-decoration: none; font-weight: 700; }
    </style>

    <div class="topo">
        <div>
            <h1>Checklist do equipamento</h1>
            <div class="sub"><strong>{{ $ativo->name }}</strong>{{ $ativo->patrimonio ? ' · Patrimônio '.$ativo->patrimonio : '' }}{{ $ativo->category?->name ? ' · '.$ativo->category->name : '' }}</div>
        </div>
        <a class="voltar" href="{{ $this->urlDeVolta(true) }}">← {{ $osId ? 'Voltar à OS' : 'Voltar ao equipamento' }}</a>
    </div>

    @if ($this->execucaoEnviada)
        @php
            $enviada = $this->execucaoEnviada;
            $pendencias = $enviada->respostas->where('resposta', 'nao_conforme')->where('critico', false);
            $criticos = $enviada->respostas->where('resposta', 'nao_conforme')->where('critico', true);
        @endphp
        @if ($enviada->status === \App\Models\CkExecution::STATUS_REPROVADO)
            <div class="aviso r">
                <strong>Checklist reprovado</strong>
                Há item crítico não conforme. O equipamento não deve operar até a correção.
                <ul>@foreach ($criticos as $c)<li>{{ $c->item_descricao }}</li>@endforeach</ul>
            </div>
            @if ($this->osDaExecucao)
                <div class="card"><strong>OS corretiva aberta{{ $this->osDaExecucao->os_number ? ' · nº '.$this->osDaExecucao->os_number : '' }}</strong><div class="peq">Já com os itens reprovados e as fotos.</div></div>
            @elseif ($this->podeAbrirOs())
                <button type="button" class="enviar" style="background:#dc2626" wire:click="abrirOs" wire:loading.attr="disabled">Abrir OS corretiva agora</button>
                @error('os') <div class="erro" style="margin-top:10px">{{ $message }}</div> @enderror
            @else
                <div class="aviso a">Avise o responsável pela manutenção para abrir a OS corretiva.</div>
            @endif
        @elseif ($enviada->status === \App\Models\CkExecution::STATUS_APROVADO_COM_RESSALVA)
            <div class="aviso a"><strong>Aprovado, com ressalvas</strong>O equipamento pode seguir, mas os pontos abaixo ficaram registrados como pendência para a manutenção.</div>
        @else
            <div class="aviso v"><strong>Checklist aprovado</strong>Tudo conforme.</div>
        @endif
        @if ($pendencias->isNotEmpty())
            <div class="card"><h2>Pendências registradas</h2><ul>@foreach ($pendencias as $p)<li>{{ $p->item_descricao }}@if ($p->observacao) — {{ $p->observacao }}@endif</li>@endforeach</ul></div>
        @endif
        <a class="botao" href="{{ \App\Filament\Resources\CkExecutionResource::getUrl('view', ['record' => $enviada]) }}">Ver o checklist registrado</a>
        <a class="botao" href="{{ $this->urlDeVolta(true) }}">{{ $osId ? 'Voltar à OS' : 'Voltar ao equipamento' }}</a>
    @elseif ($this->templates->isEmpty())
        <div class="aviso a"><strong>Nenhum checklist se aplica a este equipamento</strong>Ligue um checklist ao grupo do equipamento ou ao grupo de checklist dele em Manutenção › Cadastros › Checklists de equipamento.</div>
    @else
        <div class="card">
            <div class="linha">
                @if ($this->templates->count() > 1)
                    <label class="campo" style="min-width:320px">Qual checklist?
                        <select wire:model.live="templateId">
                            <option value="">Escolha…</option>
                            @foreach ($this->templates as $t)<option value="{{ $t->id }}">{{ $t->nome }}</option>@endforeach
                        </select>
                    </label>
                @elseif ($this->template)
                    <div><strong>{{ $this->template->nome }}</strong></div>
                @endif
                @if ($this->template)
                    <label class="campo">Horímetro ou km agora (opcional)
                        <input type="text" inputmode="decimal" wire:model="leitura" placeholder="Ex.: 1250" style="width:180px">
                    </label>
                @endif
            </div>
            @error('leitura') <div class="erro" style="margin-top:10px">{{ $message }}</div> @enderror
        </div>

        @if ($this->template)
            @php
                $itens = $this->itens;
                $respondidos = $itens->filter(fn ($i) => filled($respostas[$i->id] ?? null) || filled($valores[$i->id] ?? null) || isset($fotosItens[$i->id]))->count();
                $secaoAtual = null;
            @endphp
            <div class="card">
                <h2>Itens a conferir <span class="progresso" style="text-transform:none;letter-spacing:0">· {{ $respondidos }} de {{ $itens->count() }} respondidos</span></h2>
                <table>
                    <thead><tr><th>Nº</th><th>Item</th><th>Resposta</th></tr></thead>
                    <tbody>
                    @foreach ($itens as $item)
                        @php
                            $resposta = $respostas[$item->id] ?? null;
                            $naoConforme = $resposta === 'nao_conforme';
                        @endphp
                        @if ($secaoAtual !== $item->section_id)
                            @php $secaoAtual = $item->section_id; @endphp
                            <tr class="secao"><td colspan="3">{{ $item->secao?->titulo }}</td></tr>
                        @endif
                        <tr wire:key="ckd-item-{{ $item->id }}">
                            <td class="n">{{ $loop->iteration }}</td>
                            <td>
                                {{ $item->descricao }}@if ($item->critico)<span class="cr">CRÍTICO</span>@endif
                                @if ($item->referencia_manual)<span class="ref">Ref.: {{ $item->referencia_manual }}</span>@endif
                            </td>
                            <td class="resp">
                                @if ($item->tipo_resposta === 'conforme_nao_conforme_na')
                                    <div class="btns">
                                        <button type="button" class="b ok {{ $resposta === 'conforme' ? 'on' : '' }}" wire:click="marcar('{{ $item->id }}', 'conforme')">OK</button>
                                        <button type="button" class="b nc {{ $naoConforme ? 'on' : '' }}" wire:click="marcar('{{ $item->id }}', 'nao_conforme')">Problema</button>
                                        <button type="button" class="b na {{ $resposta === 'na' ? 'on' : '' }}" wire:click="marcar('{{ $item->id }}', 'na')">Não se aplica</button>
                                    </div>
                                @elseif ($item->tipo_resposta === 'numerico')
                                    <input type="text" inputmode="decimal" wire:model="valores.{{ $item->id }}" placeholder="Valor medido{{ $item->unidade ? ' ('.$item->unidade.')' : '' }}" style="width:100%">
                                @elseif ($item->tipo_resposta === 'selecao')
                                    <select wire:model="valores.{{ $item->id }}" style="width:100%"><option value="">Escolha…</option>@foreach ((array) $item->opcoes as $opcao)<option value="{{ $opcao }}">{{ $opcao }}</option>@endforeach</select>
                                @elseif ($item->tipo_resposta === 'texto')
                                    <textarea wire:model="valores.{{ $item->id }}" rows="2" placeholder="Escreva aqui"></textarea>
                                @elseif ($item->tipo_resposta === 'foto')
                                    <label class="peq">Foto <input type="file" accept="image/*" wire:model="fotosItens.{{ $item->id }}"></label>
                                @endif
                            </td>
                        </tr>
                        @if ($naoConforme)
                            <tr class="det" wire:key="ckd-det-{{ $item->id }}">
                                <td></td>
                                <td colspan="2">
                                    <textarea wire:model="observacoesItens.{{ $item->id }}" rows="2" placeholder="Descreva o problema"></textarea>
                                    <label class="peq" style="display:block;margin-top:6px">Foto do problema{{ $item->exige_foto_se_nao_conforme ? ' (obrigatória)' : '' }}
                                        <input type="file" accept="image/*" wire:model="fotosItens.{{ $item->id }}">
                                    </label>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
            @error('itens') <div class="erro">{{ $message }}</div> @enderror
            @error('fotosItens.*') <div class="erro">{{ $message }}</div> @enderror

            <div class="card">
                <h2>Observações</h2>
                <textarea wire:model="observacoes" rows="3" placeholder="Algo mais que a manutenção precisa saber"></textarea>
                <div style="margin-top:4px">{!! \App\Support\FormHelpers::voiceButton() !!}</div>
            </div>

            <div class="card">
                <h2>Assinatura de quem fez o checklist</h2>
                <div
                    x-data="{
                        pad: null,
                        isEmpty: true,
                        feita: false,
                        initPad() {
                            this.pad = new SignaturePad(this.$refs.canvas, { penColor: '#0f172a', backgroundColor: '#f8fafc' });
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
                    <canvas x-ref="canvas"></canvas>
                    <div style="display:flex;gap:10px;margin-top:8px;align-items:center">
                        <button type="button" class="b" x-on:click="clear()">Limpar</button>
                        <button type="button" class="b ok on" x-on:click="confirmar()" x-bind:disabled="isEmpty">Confirmar assinatura</button>
                        <span x-show="feita" class="peq" style="color:#047857;font-weight:700">✔ Assinatura confirmada</span>
                        <span class="peq">Assine com o mouse ou com o dedo, na tela.</span>
                    </div>
                </div>
                @error('assinatura') <div class="erro" style="margin-top:10px">{{ $message }}</div> @enderror
            </div>

            <div class="rodape">
                <span class="progresso">{{ $respondidos }} de {{ $itens->count() }} itens respondidos</span>
                <button type="button" class="enviar" wire:click="enviar" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="enviar">Enviar checklist</span>
                    <span wire:loading wire:target="enviar">Enviando...</span>
                </button>
            </div>
        @endif
    @endif
</div>

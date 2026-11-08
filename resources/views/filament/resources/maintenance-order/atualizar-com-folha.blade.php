@php
    $rotulos = [
        'solution' => 'Serviço executado', 'horimetro_entry' => 'Horímetro atual', 'started_at' => 'Início', 'finished_at' => 'Fim',
        'hours_spent' => 'Horas trabalhadas', 'technical_notes' => 'Observações',
    ];
    $os = $this->record;
    $fmt = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d/m/Y H:i') : (is_scalar($v) && $v !== '' ? (string) $v : '—');
@endphp
<x-filament-panels::page>
    @if (! $preparo)
        <x-filament::section>
            <p class="text-sm">Fotografe a folha da O.S. <strong>{{ $os->os_number }}</strong> que o mecânico preencheu. A IA lê e você revisa antes de salvar: nada é gravado sem a sua confirmação, e cliente e equipamento nunca mudam.</p>
            @if ($this->jaAplicadaEm())
                <p class="mt-2 text-sm text-warning-600">Atenção: uma folha já foi aplicada nesta O.S. em {{ $this->jaAplicadaEm() }}.</p>
            @endif
            <form wire:submit="lerComIA" class="mt-4">
                {{ $this->form }}
                <div class="mt-4">
                    <x-filament::button type="submit" icon="heroicon-o-sparkles" wire:loading.attr="disabled" wire:target="lerComIA">Ler com IA</x-filament::button>
                </div>
            </form>
        </x-filament::section>
    @else
        <x-filament::section heading="Revise o que a IA leu">
            @foreach ($preparo['avisos'] as $aviso)
                <p class="mb-2 rounded border border-warning-500 p-2 text-sm text-warning-700">⚠ {{ $aviso }}</p>
            @endforeach

            <table class="w-full text-sm">
                <thead><tr class="text-left"><th class="w-10"></th><th>Campo</th><th>Hoje na O.S.</th><th>Lido na folha</th></tr></thead>
                <tbody>
                @foreach ($preparo['campos'] as $campo => $valor)
                    @continue(! isset($rotulos[$campo]))
                    <tr class="border-t">
                        <td><input type="checkbox" wire:model="camposEscolhidos" value="{{ $campo }}"></td>
                        <td class="font-medium">{{ $rotulos[$campo] }}</td>
                        <td>{{ $campo === 'technical_notes' ? '—' : $fmt($os->{$campo} ?? null) }}</td>
                        <td>{{ $campo === 'technical_notes' ? \Illuminate\Support\Str::limit(trim(\Illuminate\Support\Str::after($valor, (string) $os->technical_notes)), 200) : $fmt($valor) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            @if ($preparo['pecas'])
                <label class="mt-4 flex items-center gap-2 text-sm font-medium"><input type="checkbox" wire:model="aplicarPecas"> Lançar as peças lidas</label>
                <ul class="mt-2 list-disc pl-6 text-sm">
                    @foreach ($preparo['pecas'] as $p)
                        <li>{{ $p['quantity'] ?? 1 }} × {{ $p['material_nome'] ?? ($p['name'] ?? '?') }} @if(empty($p['material_id']))<em>(texto livre, sem baixa de estoque)</em>@else<em>(baixa no estoque)</em>@endif</li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-4 text-sm">
                <label class="font-medium">Situação da O.S.
                    <select wire:model="novoStatus" class="ml-2 rounded border-gray-300">
                        <option value="manter">Manter ({{ $os->status }})</option>
                        <option value="Em Andamento">Em Andamento</option>
                        <option value="Concluída">Concluída</option>
                    </select>
                </label>
            </div>

            @if ($this->jaAplicadaEm())
                <label class="mt-4 flex items-center gap-2 text-sm text-warning-700"><input type="checkbox" wire:model="confirmaReaplicar"> Uma folha já foi aplicada em {{ $this->jaAplicadaEm() }}. Aplicar de novo mesmo assim.</label>
            @endif

            <div class="mt-6 flex gap-3">
                <x-filament::button wire:click="aplicar" icon="heroicon-o-check">Aplicar na O.S.</x-filament::button>
                <x-filament::button color="gray" wire:click="descartar">Descartar e ler outra foto</x-filament::button>
            </div>
        </x-filament::section>
    @endif

    {{-- Checklist em papel: a IA le a folha do checklist, a pessoa confere e registra, amarrado a esta O.S. --}}
    @if ($this->ckModelos()->isNotEmpty())
        <x-filament::section heading="Checklist em papel" data-testid="ck-papel">
            @if ($this->ckPendentes() > 0)
                <p class="mb-3 rounded border border-warning-500 p-2 text-sm text-warning-700">⚠ {{ $this->ckPendentes() === 1 ? 'Há 1 checklist do equipamento ainda não feito' : 'Há '.$this->ckPendentes().' checklists do equipamento ainda não feitos' }} nesta O.S. No Modo Campo ela só conclui depois de feitos.</p>
            @endif
            <p class="text-sm">Fotografe a folha do checklist que o mecânico preencheu. A IA lê as marcações e você <strong>confere item por item</strong> antes de registrar: nada é gravado antes disso. O checklist fica registrado nesta O.S., com a foto da folha como evidência.</p>

            <label class="mt-4 block text-sm font-medium">Qual checklist?
                <select wire:model.live="ckTemplateId" class="mt-1 block w-full max-w-md rounded border-gray-300">
                    <option value="">Escolha…</option>
                    @foreach ($this->ckModelos() as $m)<option value="{{ $m->id }}">{{ $m->nome }}</option>@endforeach
                </select>
            </label>

            @if ($ckTemplateId)
                <form wire:submit="lerChecklistComIA" class="mt-4">
                    {{ $this->checklistForm }}
                    <div class="mt-4 flex gap-3">
                        <x-filament::button type="submit" icon="heroicon-o-sparkles" wire:loading.attr="disabled" wire:target="lerChecklistComIA">{{ count($ckFolhas) > 0 ? 'Ler a próxima folha com IA' : 'Ler checklist com IA' }}</x-filament::button>
                    </div>
                </form>
            @endif

            @if ($ckLinhas)
                <div class="mt-6">
                    <h3 class="text-sm font-bold">Confira o que a IA leu ({{ count($ckFolhas) }} {{ count($ckFolhas) === 1 ? 'folha' : 'folhas' }})</h3>
                    <p class="mb-2 text-xs text-gray-600">Itens com ⚠ ficaram em dúvida ou ilegíveis. Itens obrigatórios sem resposta impedem o registro. Item <strong>crítico</strong> com problema deixa o equipamento inapto e abre a O.S. corretiva.</p>
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="text-left"><th class="w-10">Nº</th><th>Item</th><th class="w-56">Resposta</th><th>Observação</th></tr></thead>
                        <tbody>
                        @foreach ($ckLinhas as $id => $l)
                            <tr class="border-t align-top" wire:key="ck-linha-{{ $id }}">
                                <td class="py-2">{{ $l['n'] }}</td>
                                <td class="py-2">{{ $l['descricao'] }} @if ($l['critico'])<strong class="text-danger-600">CRÍTICO</strong>@endif @if ($l['duvida'])<span class="text-warning-700">⚠ conferir</span>@endif</td>
                                <td class="py-2">
                                    @if ($l['tipo'] === 'conforme_nao_conforme_na')
                                        <select wire:model="ckLinhas.{{ $id }}.resposta" class="w-full rounded border-gray-300"><option value="">—</option><option value="conforme">OK</option><option value="nao_conforme">Problema (N/C)</option><option value="na">Não se aplica</option></select>
                                    @elseif ($l['tipo'] === 'selecao')
                                        <select wire:model="ckLinhas.{{ $id }}.valor" class="w-full rounded border-gray-300"><option value="">—</option>@foreach ($l['opcoes'] as $op)<option value="{{ $op }}">{{ $op }}</option>@endforeach</select>
                                    @elseif ($l['tipo'] === 'foto')
                                        <label class="text-xs"><input type="checkbox" wire:click="$set('ckLinhas.{{ $id }}.valor', {{ $l['valor'] ? 'null' : "'Foto registrada na folha'" }})" @checked($l['valor'])> foto na folha</label>
                                    @else
                                        <input type="text" wire:model="ckLinhas.{{ $id }}.valor" class="w-full rounded border-gray-300" placeholder="{{ $l['tipo'] === 'numerico' ? 'Valor'.($l['unidade'] ? ' ('.$l['unidade'].')' : '') : 'Texto' }}">
                                    @endif
                                </td>
                                <td class="py-2"><input type="text" wire:model="ckLinhas.{{ $id }}.obs" class="w-full rounded border-gray-300"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="block text-sm font-medium">Horímetro ou km (opcional)<input type="text" wire:model="ckLeitura" class="mt-1 block w-full rounded border-gray-300"></label>
                        <label class="block text-sm font-medium">Observações do checklist (opcional)<input type="text" wire:model="ckObservacoes" class="mt-1 block w-full rounded border-gray-300"></label>
                    </div>

                    <div class="mt-5 flex gap-3">
                        <x-filament::button wire:click="registrarChecklistLido" icon="heroicon-o-check" wire:loading.attr="disabled" wire:target="registrarChecklistLido">Registrar checklist nesta O.S.</x-filament::button>
                        <x-filament::button color="gray" wire:click="ckDescartar">Descartar e recomeçar</x-filament::button>
                    </div>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>

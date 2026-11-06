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
</x-filament-panels::page>

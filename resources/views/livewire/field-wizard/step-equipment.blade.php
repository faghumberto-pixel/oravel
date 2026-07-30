{{-- Etapa 1: confirmar que e' ESTE equipamento antes de qualquer edicao. --}}

<div class="rounded-2xl bg-zinc-900 px-4 py-4">
    <p class="text-[11px] font-bold tracking-widest text-zinc-500">CONFIRME O EQUIPAMENTO</p>

    <p class="mt-2 text-lg font-extrabold leading-tight text-white">
        {{ $maintenanceOrder->asset?->patrimonio ?: 'SEM PATRIMÔNIO' }}
    </p>
    <p class="text-sm font-medium text-zinc-300">
        {{ $maintenanceOrder->asset?->name ?? 'Nenhum ativo vinculado a esta O.S.' }}
    </p>

    @if($maintenanceOrder->asset?->asset_tag)
        <p class="mt-1 text-xs text-zinc-500">TAG: {{ strtoupper($maintenanceOrder->asset->asset_tag) }}</p>
    @endif

    <p class="mt-3 text-xs font-semibold text-zinc-400">
        OPERAÇÃO: {{ strtoupper($maintenanceOrder->maintenance_type ?? '—') }}
    </p>
</div>

{{-- Horimetro: o unico campo obrigatorio desta etapa. Teclado numerico
     (inputmode) porque no campo se digita com luva. --}}
<div class="rounded-2xl bg-zinc-900 px-4 py-4">
    <label for="horimetro" class="block text-[11px] font-bold tracking-widest text-zinc-500">
        HORÍMETRO ATUAL <span class="text-red-400">*</span>
    </label>

    @if($this->horimetroAnterior !== null)
        <p class="mt-1 text-xs text-zinc-500">
            Última leitura registrada: {{ number_format($this->horimetroAnterior, 2, ',', '.') }}h
        </p>
    @else
        <p class="mt-1 text-xs text-zinc-500">Nenhuma leitura anterior registrada para este ativo.</p>
    @endif

    <input id="horimetro" type="number" step="0.01" min="0" inputmode="decimal"
           wire:model.blur="horimetroEntry" placeholder="0,00"
           class="mt-2 min-h-[3.25rem] w-full rounded-xl border-0 bg-zinc-800 px-4 text-lg font-bold text-zinc-100 placeholder:text-zinc-600 focus:ring-2 focus:ring-emerald-500">

    @error('horimetroEntry')
        <p class="mt-2 text-xs font-semibold text-red-400">{{ $message }}</p>
    @enderror

    @if($this->horimetroSuspeito)
        {{-- Aviso, nao trava: horimetro trocado/zerado acontece de verdade. --}}
        <p class="mt-2 text-xs font-semibold text-amber-400">
            Essa leitura é menor que a anterior. Confira antes de continuar.
        </p>
    @endif
</div>

{{-- Combustivel: opcional, botoes grandes em vez de select. --}}
<div class="rounded-2xl bg-zinc-900 px-4 py-4">
    <p class="text-[11px] font-bold tracking-widest text-zinc-500">NÍVEL DE COMBUSTÍVEL</p>

    <div class="mt-3 grid grid-cols-5 gap-2">
        @foreach(['0' => 'RES', '25' => '¼', '50' => '½', '75' => '¾', '100' => 'CHEIO'] as $value => $label)
            <button type="button" wire:click="$set('fuelLevel', '{{ $value }}')"
                    class="min-h-[2.75rem] rounded-xl text-xs font-bold transition-colors {{ (string) $fuelLevel === (string) $value ? 'bg-emerald-500 text-zinc-950' : 'bg-zinc-800 text-zinc-400' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @error('fuelLevel')
        <p class="mt-2 text-xs font-semibold text-red-400">{{ $message }}</p>
    @enderror
</div>

{{-- Historico: contexto secundario, no fim da tela pra nao competir com a acao. --}}
@if($this->recentOrders->isNotEmpty())
    <div class="rounded-2xl bg-zinc-900/60 px-4 py-4">
        <p class="text-[11px] font-bold tracking-widest text-zinc-500">HISTÓRICO RECENTE DESTE ATIVO</p>

        <ul class="mt-3 space-y-2">
            @foreach($this->recentOrders as $recent)
                <li class="flex items-baseline justify-between gap-2 text-xs">
                    <span class="font-semibold text-zinc-300">
                        {{ $recent->os_number }} · {{ $recent->maintenance_type }}
                    </span>
                    <span class="shrink-0 text-zinc-500">
                        {{ $recent->created_at->format('d/m/y') }} · {{ $recent->status }}
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endif

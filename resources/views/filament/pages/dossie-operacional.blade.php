<x-filament-panels::page>
    @php
        $order = $this->record;
    @endphp

    <div class="max-w-full space-y-6">

        {{-- Faixa de título --}}
        <div class="rounded-xl border border-gray-800 border-t-4 border-t-emerald-500 bg-gray-900 p-6 shadow-xl">
            <div class="flex items-center gap-3">
                <x-heroicon-o-chart-bar class="h-6 w-6 text-emerald-400" />
                <h2 class="text-lg font-black uppercase tracking-tight text-white">
                    Dossiê Operacional de Locação — {{ $this->getOperationLabel() }}
                    @if($order->client?->name)
                        <span class="text-gray-400">| {{ $order->client->name }}</span>
                    @endif
                </h2>
            </div>
            <p class="mt-2 text-[11px] text-gray-400">
                Monitoramento operacional da jornada do ativo pátio → obra → pátio, garantindo a auditoria fotográfica
                infalsificável com GPS e Data de Captura.
            </p>
        </div>

        {{-- 4 cards de status --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-gray-800 bg-gray-900/60 p-5 backdrop-blur-sm">
                <span class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Dossiê Operacional</span>
                <span class="mt-1 block text-2xl font-black text-gray-200">OS #{{ $order->os_number }}</span>
            </div>
            <div class="rounded-xl border border-gray-800 bg-gray-900/60 p-5 backdrop-blur-sm">
                <span class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Ativo</span>
                @if($order->asset)
                    <a href="{{ \App\Filament\Resources\AssetResource::getUrl('edit', ['record' => $order->asset]) }}"
                       class="mt-1 block truncate text-lg font-black text-emerald-400 hover:underline" title="{{ $order->asset->name }}">
                        {{ $order->asset->patrimonio ?? '—' }}
                    </a>
                    <span class="block truncate text-[11px] text-gray-400" title="{{ $order->asset->name }}">{{ $order->asset->name }}</span>
                @else
                    <span class="mt-1 block text-lg font-black text-gray-500">Ativo removido</span>
                @endif
            </div>
            <div class="rounded-xl border border-gray-800 bg-gray-900/60 p-5 backdrop-blur-sm">
                <span class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Status Operacional</span>
                <span class="mt-2 inline-flex items-center rounded-full bg-emerald-600 px-3 py-1 text-[11px] font-black uppercase text-white">
                    {{ $order->status }}
                </span>
            </div>
            <div class="rounded-xl border border-gray-800 bg-gray-900/60 p-5 backdrop-blur-sm">
                <span class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Auditoria Operacional</span>
                @if($this->isFullyAudited())
                    <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-600 px-3 py-1 text-[11px] font-black uppercase text-white">
                        <x-heroicon-o-check-circle class="h-3.5 w-3.5" /> GPS OK
                    </span>
                @else
                    <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-amber-500 px-3 py-1 text-[11px] font-black uppercase text-white">
                        <x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5" /> Pendente
                    </span>
                @endif
            </div>
        </div>

        {{-- Localização Atual --}}
        @php $localizacao = $this->getLocalizacaoAtual(); @endphp
        <div class="rounded-xl border border-gray-800 bg-gray-900 p-6 shadow-xl">
            <div class="mb-3 flex items-center gap-2">
                <x-heroicon-o-map-pin class="h-5 w-5 text-emerald-400" />
                <h3 class="text-sm font-black uppercase tracking-widest text-white">Localização Atual</h3>
            </div>

            @if($localizacao)
                <p class="text-xs text-gray-400">
                    <span class="rounded-full bg-gray-800 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-300">{{ $localizacao['origem'] }}</span>
                    <span class="ml-2 font-bold text-white">{{ $localizacao['label'] }}</span>
                    @if($localizacao['address'])
                        — {{ $localizacao['address'] }}{{ $localizacao['city'] ? ', '.$localizacao['city'] : '' }}{{ $localizacao['uf'] ? ' - '.$localizacao['uf'] : '' }}
                    @endif
                    @if($localizacao['condicao'])
                        <span class="ml-2 inline-flex items-center rounded-full bg-amber-600/20 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-400">{{ $localizacao['condicao'] }}</span>
                    @endif
                </p>
            @else
                <p class="text-[11px] text-gray-500">Sem localização definida (nem contrato vigente, nem unidade base cadastrada no Ativo).</p>
            @endif
        </div>

        {{-- Auditoria fotográfica --}}
        <div class="rounded-xl border border-gray-800 bg-gray-900 p-6 shadow-xl">
            <div class="mb-4 flex items-center gap-2">
                <x-heroicon-o-camera class="h-5 w-5 text-emerald-400" />
                <h3 class="text-sm font-black uppercase tracking-widest text-white">
                    Auditoria Fotográfica de {{ $this->getOperationLabel() }}
                </h3>
            </div>

            @php $evidencesByCategory = $this->getEvidencesByCategory(); @endphp

            @if($evidencesByCategory->isEmpty())
                <div class="rounded-lg border border-dashed border-gray-700 p-8 text-center text-[11px] text-gray-500">
                    Nenhuma evidência fotográfica registrada para esta operação.
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($evidencesByCategory as $category => $evidences)
                        @foreach($evidences as $evidence)
                            @php $isAvaria = $evidence->severity === 'avaria'; @endphp
                            <div class="overflow-hidden rounded-lg border border-gray-800 bg-gray-950">
                                <div class="{{ $isAvaria ? 'bg-red-600' : 'bg-emerald-600' }} px-3 py-1.5 text-[10px] font-black uppercase text-white">
                                    {{ $category }}
                                </div>

                                @if($evidence->file_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($evidence->file_path) }}" class="h-48 w-full object-cover" />
                                @endif

                                <div class="space-y-1 p-3 font-mono text-[10px] text-gray-400">
                                    <div class="flex items-center gap-1.5 text-gray-300">
                                        <x-heroicon-o-shield-check class="h-3.5 w-3.5 text-emerald-500" />
                                        <span class="font-bold">Auditoria Operacional Infalsificável:</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <x-heroicon-o-map-pin class="h-3.5 w-3.5 shrink-0" />
                                        <span>Lat: {{ $evidence->latitude ?? '—' }}, Long: {{ $evidence->longitude ?? '—' }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <x-heroicon-o-camera class="h-3.5 w-3.5 shrink-0" />
                                        <span>Capturada em: {{ optional($evidence->captured_at)->format('d/m/Y H:i:s') }} (Infalsificável)</span>
                                    </div>
                                    @if($evidence->observation)
                                        <div class="pt-1 text-gray-300">{{ $evidence->observation }}</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Rota do transporte (checkpoints de rastreamento manual) --}}
        @foreach($order->equipmentMovements as $movement)
            @if($movement->locations->isNotEmpty())
                <div class="rounded-xl border border-gray-800 bg-gray-900 p-2 shadow-xl">
                    @livewire(\App\Filament\Widgets\EquipmentMovementRouteMapWidget::class, ['equipmentMovement' => $movement], key('rota-'.$movement->id))
                </div>
            @endif
        @endforeach

        {{-- Diagnóstico + Validação Jurídica --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-gray-800 bg-gray-900 p-6 shadow-xl">
                <div class="mb-3 flex items-center gap-2">
                    <x-heroicon-o-wrench-screwdriver class="h-5 w-5 text-primary-400" />
                    <h3 class="text-sm font-black uppercase tracking-widest text-white">Diagnóstico Técnico Final (Técnico Responsável)</h3>
                </div>
                <div class="rounded-lg bg-gray-950 p-4 font-mono text-[11px] text-gray-300">
                    <span class="font-bold text-gray-100">{{ $order->technician?->name ?? 'Técnico não atribuído' }}:</span>
                    <p class="mt-1 whitespace-pre-line">{{ $order->technical_notes ?: 'Nenhum diagnóstico registrado.' }}</p>
                </div>
                <p class="mt-3 text-[10px] text-gray-500">
                    Registrado em: {{ optional($order->finished_at ?? $order->updated_at)->format('d/m/Y H:i') }} | Assinado Digitalmente
                </p>
            </div>

            <div class="rounded-xl border border-gray-800 bg-gray-900 p-6 shadow-xl">
                <div class="mb-3 flex items-center gap-2">
                    <x-heroicon-o-user-circle class="h-5 w-5 text-primary-400" />
                    <h3 class="text-sm font-black uppercase tracking-widest text-white">Validação Jurídica (Assinatura do Cliente)</h3>
                </div>
                <div class="flex flex-col items-center justify-center rounded-lg bg-gray-950 p-4 text-center">
                    @if($order->client_signature)
                        <img src="{{ $order->client_signature }}" class="mb-2 h-20 object-contain" />
                    @endif
                    <span class="text-[11px] font-bold text-gray-300">Assinado Digitalmente: (Simulação Demo)</span>
                    <span class="mt-1 text-[10px] text-gray-500">
                        Cliente: {{ $order->client?->name ?? 'N/A' }} | Representante: {{ $order->client?->name ?? 'N/A' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Rodapé --}}
        <div class="flex flex-col items-stretch justify-end gap-3 sm:flex-row">
            <a href="{{ route('maintenance-orders.laudo-minimalista', $order) }}" target="_blank"
               class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-700 px-5 py-2.5 text-[10px] font-black uppercase text-gray-300 transition hover:bg-gray-800">
                <x-heroicon-o-eye class="h-4 w-4" />
                Visualizar (Minimalista)
            </a>
            <a href="{{ route('maintenance-orders.dossie.pdf', $order) }}" target="_blank"
               class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-[10px] font-black uppercase text-white transition hover:bg-emerald-700">
                <x-heroicon-o-document-text class="h-4 w-4" />
                Gerar Laudo Operacional Completo (PDF Jurídico)
            </a>
        </div>

    </div>
</x-filament-panels::page>

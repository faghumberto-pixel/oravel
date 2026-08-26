<x-filament-panels::page>
    @php
        $queue = $this->queueItems;
        $technicians = $this->technicians;
        $allocations = $this->allocations;
        [$periodStart, $periodEnd] = $this->periodBounds();
        $days = collect();
        $cursor = $periodStart->copy();
        while ($cursor->lte($periodEnd) && $days->count() < 31) {
            $days->push($cursor->copy());
            $cursor->addDay();
        }
    @endphp

    <div x-data="{ dragging: null, overSlot: null }" class="flex flex-col gap-4">

        {{-- ===================== CONTROLES DE PERÍODO ===================== --}}
        <div class="flex flex-wrap items-center gap-2">
            <select wire:model.live="viewMode" class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                <option value="day">Dia</option>
                <option value="week">Semana</option>
                <option value="month">Mês</option>
            </select>
            <input type="date" wire:model.live="referenceDate" class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-4 gap-4">
            {{-- ===================== FILA DE ALOCAÇÃO ===================== --}}
            <x-filament::section class="xl:col-span-1">
                <x-slot name="heading">Fila de Alocação</x-slot>

                <div class="flex flex-col gap-2 max-h-[70vh] overflow-y-auto">
                    @forelse($queue as $item)
                        <div
                            draggable="true"
                            x-on:dragstart="dragging = '{{ $item['source_id'] }}'"
                            x-on:dragend="dragging = null"
                            wire:key="queue-{{ $item['source_id'] }}"
                            class="cursor-grab active:cursor-grabbing rounded-lg border-l-4 {{ $item['allocated'] ? 'border-emerald-500 bg-emerald-500/5' : 'border-amber-500 bg-amber-500/5' }} p-2.5 text-sm"
                        >
                            <p class="font-semibold text-gray-900 dark:text-gray-100 leading-snug">{{ $item['title'] }}</p>
                            <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                                @if($item['failure_category'])
                                    <span>{{ \App\Models\MaintenanceOrder::failureCategoryLabels()[$item['failure_category']] ?? $item['failure_category'] }}</span>
                                @else
                                    <span>Preventiva</span>
                                @endif
                                @if($item['criticality'])
                                    <span
                                        class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold text-white"
                                        style="background-color: {{ $item['criticality']->color ?? '#6b7280' }}"
                                    >{{ $item['criticality']->name }}</span>
                                @endif
                            </div>
                            <p class="text-[11px] mt-1 {{ $item['allocated'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                {{ $item['allocated'] ? 'Já alocado' : 'Sem técnico — arraste para uma raia' }}
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nenhum item pendente.</p>
                    @endforelse
                </div>
            </x-filament::section>

            {{-- ===================== GANTT ===================== --}}
            <x-filament::section class="xl:col-span-3">
                <x-slot name="heading">Alocação por Técnico</x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr>
                                <th class="text-left p-2 sticky left-0 bg-white dark:bg-gray-900 w-40">Técnico</th>
                                @foreach($days as $day)
                                    <th class="p-2 text-center font-medium text-gray-500 min-w-[110px]">{{ $day->translatedFormat('D d/m') }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($technicians as $technician)
                                <tr class="border-t dark:border-gray-800">
                                    <td class="p-2 sticky left-0 bg-white dark:bg-gray-900 font-medium text-gray-900 dark:text-gray-100">
                                        {{ $technician->name }}
                                    </td>
                                    @foreach($days as $day)
                                        @php
                                            $slotStart = $day->copy()->setTime(9, 0)->toDateTimeString();
                                            $slotKey = $technician->id.'|'.$day->toDateString();
                                            $dayAllocations = $allocations->filter(fn ($a) => $a->technician_id === $technician->id && $a->starts_at->isSameDay($day));
                                        @endphp
                                        <td
                                            class="p-1 align-top border-l dark:border-gray-800 transition-colors"
                                            :class="overSlot === '{{ $slotKey }}' ? 'bg-indigo-500/10 ring-1 ring-inset ring-indigo-400/40' : ''"
                                            x-on:dragover.prevent="overSlot = '{{ $slotKey }}'"
                                            x-on:dragleave="overSlot = (overSlot === '{{ $slotKey }}') ? null : overSlot"
                                            x-on:drop.prevent="overSlot = null; if (dragging) { $wire.allocate(dragging, '{{ $technician->id }}', '{{ $slotStart }}'); dragging = null; }"
                                        >
                                            @forelse($dayAllocations as $allocation)
                                                <div wire:key="alloc-{{ $allocation->id }}" class="rounded bg-indigo-600 text-white text-[10px] px-1.5 py-1 mb-1">
                                                    {{ $allocation->maintenanceOrder?->asset?->name ?? 'Alocação' }}
                                                </div>
                                            @empty
                                                <div class="h-8 rounded border border-dashed border-gray-300 dark:border-gray-700"></div>
                                            @endforelse
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td class="p-4 text-center text-gray-500" colspan="{{ $days->count() + 1 }}">Nenhum técnico disponível.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>

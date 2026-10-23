<x-mobile.shell title="Minhas Faltas" subtitle="Registre uma ausência e acompanhe o histórico" :back="route('filament.admin.pages.app-colaborador')">
    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
        <p class="mb-3 text-xs font-bold uppercase tracking-wide text-zinc-600">Registrar falta/ausência</p>
        <form wire:submit.prevent="enviar" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-zinc-700">De</label>
                    <input type="date" wire:model="start_date" class="w-full rounded-xl border-zinc-300 bg-white text-sm text-zinc-900">
                    @error('start_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-zinc-700">Até</label>
                    <input type="date" wire:model="end_date" class="w-full rounded-xl border-zinc-300 bg-white text-sm text-zinc-900">
                    @error('end_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold text-zinc-700">Motivo</label>
                <textarea wire:model="reason" rows="3" class="w-full rounded-xl border-zinc-300 bg-white text-sm text-zinc-900 placeholder-zinc-400" placeholder="Ex: consulta médica, atestado anexado"></textarea>
                @error('reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold text-zinc-700">Atestado (opcional)</label>
                <input type="file" wire:model="attachment" accept="application/pdf,image/jpeg,image/png" class="w-full text-xs text-zinc-700 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-200 file:px-3 file:py-2 file:text-xs file:font-bold file:text-zinc-800">
                <div wire:loading wire:target="attachment" class="mt-1 text-xs text-amber-700">Enviando arquivo...</div>
                @error('attachment') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @if ($attachment)
                    <p class="mt-1 text-xs text-emerald-700">✓ {{ $attachment->getClientOriginalName() }}</p>
                @endif
            </div>

            <button type="submit" class="min-h-[3rem] w-full rounded-xl bg-blue-600 text-sm font-bold text-white transition hover:bg-blue-700 active:scale-95">
                Enviar
            </button>
        </form>
    </div>

    <div class="space-y-2">
        <p class="text-xs font-bold uppercase tracking-wide text-zinc-600">Histórico</p>

        @forelse ($this->minhasFaltas as $falta)
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-zinc-900">
                            {{ $falta->start_date->format('d/m/Y') }}
                            @if (! $falta->start_date->equalTo($falta->end_date))
                                – {{ $falta->end_date->format('d/m/Y') }}
                            @endif
                        </p>
                        <p class="truncate text-xs text-zinc-600">{{ $falta->reason }}</p>
                        @if ($falta->attachment_path)
                            <p class="text-[11px] text-zinc-600">📎 Atestado anexado</p>
                        @endif
                        @if ($falta->status === \App\Models\Absence::STATUS_REJEITADO && $falta->review_notes)
                            <p class="mt-1 text-xs text-red-600">Motivo: {{ $falta->review_notes }}</p>
                        @endif
                    </div>
                    <span @class([
                        'shrink-0 rounded-full px-2 py-1 text-[10px] font-bold uppercase',
                        'bg-amber-100 text-amber-800' => $falta->status === \App\Models\Absence::STATUS_PENDENTE,
                        'bg-emerald-100 text-emerald-800' => $falta->status === \App\Models\Absence::STATUS_APROVADO,
                        'bg-red-100 text-red-700' => $falta->status === \App\Models\Absence::STATUS_REJEITADO,
                    ])>
                        {{ \App\Models\Absence::statusLabels()[$falta->status] ?? $falta->status }}
                    </span>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-zinc-200 bg-white p-6 text-center shadow-sm">
                <p class="text-sm text-zinc-600">Nenhuma falta registrada ainda.</p>
            </div>
        @endforelse
    </div>
</x-mobile.shell>

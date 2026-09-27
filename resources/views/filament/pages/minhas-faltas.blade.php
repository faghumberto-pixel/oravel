<div class="absolute inset-0 mx-auto flex max-w-md flex-col overflow-y-auto bg-slate-950">
    <header class="sticky top-0 z-10 flex items-center gap-3 border-b border-slate-800 bg-slate-900/95 px-5 py-4 backdrop-blur">
        <a href="{{ route('filament.admin.pages.app-colaborador') }}" class="text-slate-400 text-lg leading-none">←</a>
        <h1 class="text-lg font-black text-white">Minhas Faltas</h1>
    </header>

    <main class="flex-1 space-y-4 px-5 py-4">
        <div class="rounded-2xl bg-slate-900 p-4">
            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Registrar falta/ausência</p>
            <form wire:submit.prevent="enviar" class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold text-slate-400">De</label>
                        <input type="date" wire:model="start_date" class="w-full rounded-xl border-slate-700 bg-slate-800 text-sm text-white">
                        @error('start_date') <p class="mt-1 text-[11px] text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold text-slate-400">Até</label>
                        <input type="date" wire:model="end_date" class="w-full rounded-xl border-slate-700 bg-slate-800 text-sm text-white">
                        @error('end_date') <p class="mt-1 text-[11px] text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-[11px] font-semibold text-slate-400">Motivo</label>
                    <textarea wire:model="reason" rows="3" class="w-full rounded-xl border-slate-700 bg-slate-800 text-sm text-white" placeholder="Ex: consulta médica, atestado anexado"></textarea>
                    @error('reason') <p class="mt-1 text-[11px] text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-[11px] font-semibold text-slate-400">Atestado (opcional)</label>
                    <input type="file" wire:model="attachment" accept="application/pdf,image/jpeg,image/png" class="w-full text-xs text-slate-300 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-700 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white">
                    <div wire:loading wire:target="attachment" class="mt-1 text-[11px] text-amber-400">Enviando arquivo...</div>
                    @error('attachment') <p class="mt-1 text-[11px] text-red-400">{{ $message }}</p> @enderror
                    @if ($attachment)
                        <p class="mt-1 text-[11px] text-emerald-400">✓ {{ $attachment->getClientOriginalName() }}</p>
                    @endif
                </div>

                <button type="submit" class="min-h-[3rem] w-full rounded-xl bg-emerald-600 text-sm font-bold text-white active:bg-emerald-700">
                    Enviar
                </button>
            </form>
        </div>

        <div class="space-y-2">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Histórico</p>

            @forelse ($this->minhasFaltas as $falta)
                <div class="rounded-2xl bg-slate-900 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-white">
                                {{ $falta->start_date->format('d/m/Y') }}
                                @if (! $falta->start_date->equalTo($falta->end_date))
                                    – {{ $falta->end_date->format('d/m/Y') }}
                                @endif
                            </p>
                            <p class="truncate text-xs text-slate-400">{{ $falta->reason }}</p>
                            @if ($falta->attachment_path)
                                <p class="text-[10px] text-slate-500">📎 Atestado anexado</p>
                            @endif
                            @if ($falta->status === \App\Models\Absence::STATUS_REJEITADO && $falta->review_notes)
                                <p class="mt-1 text-[11px] text-red-400">Motivo: {{ $falta->review_notes }}</p>
                            @endif
                        </div>
                        <span @class([
                            'shrink-0 rounded-full px-2 py-1 text-[10px] font-bold uppercase',
                            'bg-amber-500/20 text-amber-400' => $falta->status === \App\Models\Absence::STATUS_PENDENTE,
                            'bg-emerald-500/20 text-emerald-400' => $falta->status === \App\Models\Absence::STATUS_APROVADO,
                            'bg-red-500/20 text-red-400' => $falta->status === \App\Models\Absence::STATUS_REJEITADO,
                        ])>
                            {{ \App\Models\Absence::statusLabels()[$falta->status] ?? $falta->status }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl bg-slate-900 p-6 text-center">
                    <p class="text-sm text-slate-500">Nenhuma falta registrada ainda.</p>
                </div>
            @endforelse
        </div>
    </main>
</div>

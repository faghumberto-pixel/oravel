<div class="space-y-4">
    {{-- Descrição do problema --}}
    <div class="rounded-2xl bg-zinc-900 p-4">
        <label class="text-xs font-bold uppercase tracking-wide text-zinc-400">Descrição do Problema</label>
        <textarea
            wire:model="damageDescription"
            rows="3"
            placeholder="Descreva o problema encontrado..."
            class="mt-2 w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-emerald-500"
        ></textarea>
    </div>

    {{-- Notas técnicas com voz --}}
    <div class="rounded-2xl bg-zinc-900 p-4">
        <label class="text-xs font-bold uppercase tracking-wide text-zinc-400">Notas Técnicas</label>
        <textarea
            wire:model="technicalNotes"
            rows="2"
            placeholder="Observações técnicas adicionais..."
            class="mt-2 w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-emerald-500"
        ></textarea>

        {{-- Componente de voz --}}
        <div class="mt-3 border-t border-zinc-800 pt-3">
            @livewire('voice-input', ['modelName' => 'technicalNotes'], key('voice-technical-notes-' . uniqid()))
        </div>
    </div>

    {{-- Fotos ANTES/DEPOIS --}}
    <div class="space-y-3">
        {{-- Foto ANTES --}}
        <div class="rounded-2xl bg-zinc-900 p-4">
            <label class="text-xs font-bold uppercase tracking-wide text-zinc-400">Foto - Antes</label>
            <p class="mt-1 text-[11px] text-zinc-500">Tire uma foto mostrando o equipamento antes do reparo</p>

            @if ($damagePhotoBefore)
                <div class="mt-3 relative h-32 w-full">
                    <img
                        src="{{ $damagePhotoBefore->temporaryUrl() }}"
                        alt="Foto antes"
                        class="h-32 w-full rounded-xl object-cover"
                    >
                    <button
                        type="button"
                        wire:click="clearDamagePhotoBefore"
                        class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white hover:bg-red-600"
                    >
                        ✕
                    </button>
                </div>
            @else
                <label class="mt-3 flex min-h-[8rem] cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-zinc-700 hover:border-zinc-600">
                    <input type="file" accept="image/*" capture="environment" wire:model="damagePhotoBefore" class="hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6 text-zinc-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="text-sm font-semibold text-zinc-400">Adicionar foto</span>
                </label>
            @endif
            <div wire:loading wire:target="damagePhotoBefore" class="mt-2 text-[11px] text-zinc-500">Enviando foto...</div>
        </div>

        {{-- Foto DEPOIS --}}
        <div class="rounded-2xl bg-zinc-900 p-4">
            <label class="text-xs font-bold uppercase tracking-wide text-zinc-400">Foto - Depois</label>
            <p class="mt-1 text-[11px] text-zinc-500">Tire uma foto mostrando o equipamento após o reparo</p>

            @if ($damagePhotoAfter)
                <div class="mt-3 relative h-32 w-full">
                    <img
                        src="{{ $damagePhotoAfter->temporaryUrl() }}"
                        alt="Foto depois"
                        class="h-32 w-full rounded-xl object-cover"
                    >
                    <button
                        type="button"
                        wire:click="clearDamagePhotoAfter"
                        class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white hover:bg-red-600"
                    >
                        ✕
                    </button>
                </div>
            @else
                <label class="mt-3 flex min-h-[8rem] cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-zinc-700 hover:border-zinc-600">
                    <input type="file" accept="image/*" capture="environment" wire:model="damagePhotoAfter" class="hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6 text-zinc-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="text-sm font-semibold text-zinc-400">Adicionar foto</span>
                </label>
            @endif
            <div wire:loading wire:target="damagePhotoAfter" class="mt-2 text-[11px] text-zinc-500">Enviando foto...</div>
        </div>
    </div>

    {{-- Registro de Avaria (opcional) --}}
    <div class="rounded-2xl border border-zinc-800 bg-zinc-900/50 p-4">
        <label class="flex items-center gap-2">
            <input
                type="checkbox"
                wire:model="shouldRegisterDamage"
                class="rounded border-zinc-700 bg-zinc-800 text-emerald-500 focus:ring-emerald-500"
            >
            <span class="text-xs font-bold uppercase tracking-wide text-zinc-400">Registrar como Avaria Formal</span>
        </label>
        <p class="mt-2 text-[11px] text-zinc-500">Marque para criar um registro de avaria que pode ser consultado depois</p>
    </div>
</div>

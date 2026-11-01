<div class="space-y-4">
    @foreach (['Abordagem' => $abordagem, 'Segundo contato (3 a 4 dias depois, só se não houver resposta)' => $followup] as $titulo => $texto)
        <div x-data="{ ok: false }" class="space-y-2">
            <div class="text-sm font-semibold">{{ $titulo }}</div>
            <div class="whitespace-pre-wrap rounded-lg border border-gray-200 p-3 text-sm dark:border-white/10">{{ $texto }}</div>
            <x-filament::button size="sm" x-on:click="navigator.clipboard.writeText(@js($texto)); ok = true; setTimeout(() => ok = false, 1500)">
                <span x-text="ok ? 'Copiado' : 'Copiar mensagem'"></span>
            </x-filament::button>
        </div>
    @endforeach
</div>

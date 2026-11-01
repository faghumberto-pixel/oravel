<div class="space-y-4">
    @if (filled($olhar ?? null))
        <div class="rounded-lg border-l-4 border-primary-500 bg-gray-50 p-3 text-sm dark:bg-white/5"><b>Antes de enviar:</b> {{ $olhar }}</div>
    @endif
    @foreach (['Abordagem' => $abordagem, 'Segundo contato (3 a 4 dias depois, só se não houver resposta)' => $followup] as $titulo => $texto)
        <div x-data="{ ok: false }" class="space-y-2">
            <div class="text-sm font-semibold">{{ $titulo }}</div>
            <div class="whitespace-pre-wrap rounded-lg border border-gray-200 p-3 text-sm dark:border-white/10">{{ $texto }}</div>
            <x-filament::button size="sm" x-on:click="navigator.clipboard.writeText(@js($texto)); ok = true; setTimeout(() => ok = false, 1500)">
                <span x-text="ok ? 'Copiado' : 'Copiar mensagem'"></span>
            </x-filament::button>
        </div>
    @endforeach

    <div class="space-y-2">
        <div class="text-sm font-semibold">Se o sócio responder</div>
        @foreach ($objecoes as $pergunta => $resposta)
            <div class="rounded-lg border border-gray-200 p-3 text-sm dark:border-white/10">
                <div class="font-semibold">{{ $pergunta }}</div>
                <div>{{ $resposta }}</div>
            </div>
        @endforeach
    </div>
</div>

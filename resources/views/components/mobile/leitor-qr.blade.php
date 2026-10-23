{{-- Botao "Ler QR do equipamento": abre a camera, le o QR da etiqueta e chama $wire.{metodo}(texto).
     A etiqueta traz o link do dossie (.../dossie-mobile/{id}) ou so o patrimonio/placa.
     JS em resources/js/leitor-qr.js (window.leitorQr). --}}
@props(['metodo' => 'buscarPorCodigo'])

<div x-data="leitorQr('{{ $metodo }}')" x-on:livewire:navigating.window="parar()" class="space-y-2">
    <button type="button" x-show="!aberto" x-on:click="abrir()"
            class="flex min-h-[3rem] w-full items-center justify-center gap-2 rounded-xl bg-blue-600 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 active:scale-95">
        <span aria-hidden="true">📷</span> Ler QR do equipamento
    </button>

    <div x-show="aberto" x-cloak class="space-y-2">
        <video x-ref="video" playsinline muted class="aspect-square w-full rounded-xl bg-black object-cover"></video>
        <button type="button" x-on:click="parar()"
                class="min-h-[2.75rem] w-full rounded-xl border border-zinc-300 bg-white text-sm font-bold text-zinc-700 active:bg-zinc-50">
            Cancelar leitura
        </button>
    </div>

    <p x-show="aviso" x-cloak x-text="aviso" class="rounded-lg bg-zinc-100 p-3 text-xs text-zinc-700"></p>
</div>

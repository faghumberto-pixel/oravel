<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            capturing: false,
            // Redimensiona/recomprime no canvas antes de guardar como base64
            // -- pedido do usuario 2026-07-29: foto de celular sem tratamento
            // (3-8MB) e' guardada direto no estado do Livewire, que reenvia o
            // formulario inteiro (com TODAS as fotos ja tiradas) a cada acao
            // na mesma tela -- inclusive so' pra adicionar um material.
            // Depois de 2-3 fotos isso estoura post_max_size e vira erro 413.
            // Reduzindo pro lado maior de 1600px + JPEG 72%, uma foto tipica
            // de 5MB vira ~150-300KB -- resolve na raiz, sem depender de
            // configuracao de servidor.
            async compressImage(file) {
                const dataUrl = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = (e) => resolve(e.target.result);
                    reader.onerror = reject;
                    reader.readAsDataURL(file);
                });

                const img = await new Promise((resolve, reject) => {
                    const el = new Image();
                    el.onload = () => resolve(el);
                    el.onerror = reject;
                    el.src = dataUrl;
                });

                const maxSide = 1600;
                const scale = Math.min(1, maxSide / Math.max(img.width, img.height));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(img.width * scale);
                canvas.height = Math.round(img.height * scale);

                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                return canvas.toDataURL('image/jpeg', 0.72);
            },
            async handleFile(event) {
                const file = event.target.files[0];
                if (!file) return;

                this.capturing = true;

                let compressed;
                try {
                    compressed = await this.compressImage(file);
                } catch (e) {
                    // Se o canvas falhar por algum motivo, cai pro arquivo
                    // original em vez de travar a captura -- pior o
                    // tamanho do que perder a foto.
                    compressed = await new Promise((resolve) => {
                        const reader = new FileReader();
                        reader.onload = (e) => resolve(e.target.result);
                        reader.readAsDataURL(file);
                    });
                }

                const payload = {
                    image: compressed,
                    latitude: null,
                    longitude: null,
                    captured_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
                };

                const finish = () => {
                    this.state = payload;
                    this.capturing = false;
                    event.target.value = '';
                };

                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            payload.latitude = pos.coords.latitude;
                            payload.longitude = pos.coords.longitude;
                            finish();
                        },
                        () => finish(),
                        { timeout: 5000, maximumAge: 60000 }
                    );
                } else {
                    finish();
                }
            },
            clear() {
                this.state = null;
            },
        }"
    >
        <input
            type="file"
            accept="image/*"
            capture="environment"
            x-ref="input"
            x-on:change="handleFile($event)"
            class="hidden"
        />

        <template x-if="!state">
            <button
                type="button"
                x-on:click="$refs.input.click()"
                x-bind:disabled="capturing"
                class="flex w-full flex-col items-center justify-center gap-y-2 rounded-lg border-2 border-dashed border-gray-300 p-6 text-gray-500 transition hover:border-primary-400 hover:text-primary-600 disabled:opacity-50 dark:border-gray-600 dark:text-gray-400"
            >
                <svg x-show="!capturing" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.174C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.174 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.822 1.316Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                </svg>
                <svg x-show="capturing" x-cloak class="h-8 w-8 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span x-text="capturing ? 'Obtendo localização...' : 'Tirar Foto'" class="text-sm font-medium"></span>
            </button>
        </template>

        <template x-if="state">
            <div class="relative">
                <img :src="state.image" class="h-48 w-full rounded-lg border border-gray-300 object-cover dark:border-gray-600" />
                <button
                    type="button"
                    x-on:click="clear()"
                    class="absolute right-2 top-2 rounded-full bg-white/90 p-1.5 text-danger-600 shadow hover:bg-white dark:bg-gray-900/90"
                    title="Trocar foto"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                </button>
                <div x-show="state.latitude" x-cloak class="mt-1 flex items-center gap-x-1 text-xs text-gray-500 dark:text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3.5 w-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                    </svg>
                    <span>Localização registrada</span>
                </div>
            </div>
        </template>
    </div>
</x-dynamic-component>

@extends('layouts.colaborador')
@section('titulo', 'Ler QR - Oravel Colaborador')
@section('conteudo')
    <a href="{{ route('colab.home') }}" class="text-sm font-semibold text-zinc-400">← Início</a>
    <h1 class="mt-3 text-xl font-bold">Ler QR do equipamento</h1>

    <div x-data="leitorQr('{{ route('colab.dossie') }}')" class="mt-4">
        <video x-ref="video" playsinline muted class="aspect-square w-full rounded-2xl bg-black object-cover" x-show="ativo"></video>
        <button x-show="!ativo && suportado" @click="iniciar()" type="button" class="min-h-[3rem] w-full rounded-xl bg-orange-500 text-base font-bold text-white">Abrir câmera</button>
        <p x-show="!suportado" x-cloak class="rounded-lg bg-zinc-900 p-3 text-sm text-zinc-300">Este navegador não liberou a câmera. Abra o link no Chrome (Android) ou no Safari (iPhone), ou digite o patrimônio abaixo.</p>
        <p x-text="erro" x-show="erro" class="mt-2 text-sm text-red-400"></p>

        <form method="GET" action="{{ route('colab.dossie') }}" class="mt-6" onsubmit="event.preventDefault(); const v = this.q.value.trim(); if (v) window.location = this.action + '?busca=' + encodeURIComponent(v);">
            <label class="mb-1 block text-xs font-semibold uppercase text-zinc-400" for="q">Ou digite o patrimônio ou a placa</label>
            <input id="q" name="q" class="min-h-[3rem] w-full rounded-xl border border-zinc-700 bg-zinc-900 px-4 text-base text-white" autocomplete="off">
            <button type="submit" class="mt-3 min-h-[3rem] w-full rounded-xl bg-zinc-800 text-base font-bold text-white">Buscar</button>
        </form>
    </div>

    <script src="/vendor/jsqr.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('leitorQr', (base) => ({
                ativo: false, erro: '', stream: null, detector: null,
                temCamera: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
                get suportado() { return this.temCamera; },
                async iniciar() {
                    this.erro = '';
                    try {
                        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } } });
                        const video = this.$refs.video;
                        video.srcObject = this.stream;
                        await video.play();
                        this.ativo = true;
                        // Leitor nativo quando existe; senão (muitos Androids) o jsQR embutido lê o quadro da câmera.
                        try { this.detector = 'BarcodeDetector' in window ? new BarcodeDetector({ formats: ['qr_code'] }) : null; } catch (e) { this.detector = null; }
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d', { willReadFrequently: true });
                        const ler = async () => {
                            if (!this.ativo) return;
                            let texto = null;
                            if (video.videoWidth) {
                                if (this.detector) {
                                    try { const [c] = await this.detector.detect(video); texto = c ? c.rawValue : null; } catch (e) { this.detector = null; }
                                }
                                if (!this.detector && texto === null) {
                                    const escala = Math.min(1, 480 / video.videoWidth);
                                    canvas.width = Math.round(video.videoWidth * escala);
                                    canvas.height = Math.round(video.videoHeight * escala);
                                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                                    const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                    const c = window.jsQR ? jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' }) : null;
                                    texto = c ? c.data : null;
                                }
                            }
                            if (texto) { this.parar(); this.abrir(texto); return; }
                            setTimeout(ler, 150);
                        };
                        ler();
                    } catch (e) { this.erro = 'Não foi possível abrir a câmera. Libere a permissão do navegador e tente de novo.'; }
                },
                parar() { this.ativo = false; if (this.stream) this.stream.getTracks().forEach(t => t.stop()); },
                abrir(texto) {
                    // A etiqueta traz o link do dossiê (.../dossie-mobile/{id}) ou só o patrimônio.
                    const m = texto.match(/dossie-mobile\/([0-9a-f-]{8,})/i);
                    window.location = m ? base + '/' + m[1] : base + '?busca=' + encodeURIComponent(texto);
                },
            }));
        });
    </script>
@endsection

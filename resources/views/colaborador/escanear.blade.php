@extends('layouts.colaborador')
@section('titulo', 'Ler QR - Oravel Colaborador')
@section('voltar', route('colab.home'))
@section('voltar_rotulo', 'Início')
@section('tela', 'Ler QR do equipamento')
@section('conteudo')
    {{-- JavaScript puro: esta página não carrega Livewire/Alpine (x-data, @click e x-show não funcionariam aqui). --}}

    <div class="mt-4">
        <video id="qr-video" playsinline muted class="hidden aspect-square w-full rounded-xl bg-zinc-50 object-cover"></video>
        <button id="qr-abrir" type="button" class="min-h-[3rem] w-full rounded-xl bg-blue-600 text-base font-bold text-white active:bg-blue-600">Abrir câmera</button>
        <p id="qr-aviso" class="mt-3 hidden rounded-lg bg-white p-3 text-sm text-zinc-700 border border-zinc-200 shadow-sm"></p>

        <form id="qr-form" class="mt-6">
            <label class="mb-1 block text-xs font-semibold uppercase text-zinc-600" for="q">Ou digite o patrimônio ou a placa</label>
            <input id="q" name="q" autocomplete="off" class="min-h-[3rem] w-full rounded-xl border border-zinc-300 bg-white px-4 text-base uppercase text-zinc-900">
            <button type="submit" class="mt-3 min-h-[3rem] w-full rounded-xl bg-zinc-100 text-base font-bold text-zinc-900 active:bg-zinc-200">Buscar</button>
        </form>
    </div>

    <script src="/vendor/jsqr.js"></script>
    <script>
        (function () {
            const base = @json(route('colab.dossie', [], false));
            const video = document.getElementById('qr-video');
            const botao = document.getElementById('qr-abrir');
            const aviso = document.getElementById('qr-aviso');
            const form = document.getElementById('qr-form');
            let stream = null, ativo = false, detector = null;

            const mostrar = (texto) => { aviso.textContent = texto; aviso.classList.remove('hidden'); };

            function abrir(texto) {
                // A etiqueta traz o link do dossiê (.../dossie-mobile/{id}) ou só o patrimônio/placa.
                const m = texto.match(/dossie-mobile\/([0-9a-f-]{8,})/i);
                window.location = m ? base + '/' + m[1] : base + '?busca=' + encodeURIComponent(texto.trim());
            }

            function parar() {
                ativo = false;
                if (stream) stream.getTracks().forEach((t) => t.stop());
            }

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const v = document.getElementById('q').value.trim();
                if (v) abrir(v);
            });

            if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)) {
                botao.classList.add('hidden');
                mostrar(window.isSecureContext
                    ? 'Este navegador não liberou a câmera. Abra o link no Chrome (Android) ou no Safari (iPhone), ou digite o patrimônio ou a placa abaixo.'
                    : 'A câmera só funciona em página segura (https). Abra o endereço começando com https:// ou digite o patrimônio ou a placa abaixo.');
                return;
            }

            botao.addEventListener('click', async () => {
                aviso.classList.add('hidden');
                botao.disabled = true;
                botao.textContent = 'Abrindo a câmera…';
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                    video.srcObject = stream;
                    await video.play();
                    ativo = true;
                    video.classList.remove('hidden');
                    botao.classList.add('hidden');
                    mostrar('Aponte a câmera para o QR da etiqueta do equipamento.');
                    // Leitor nativo quando existe; senão (muitos Androids) o jsQR embutido lê o quadro da câmera.
                    try { detector = 'BarcodeDetector' in window ? new BarcodeDetector({ formats: ['qr_code'] }) : null; } catch (e) { detector = null; }
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d', { willReadFrequently: true });
                    const ler = async () => {
                        if (!ativo) return;
                        let texto = null;
                        if (video.videoWidth) {
                            if (detector) {
                                try { const r = await detector.detect(video); texto = r.length ? r[0].rawValue : null; } catch (e) { detector = null; }
                            }
                            if (!detector && texto === null) {
                                const escala = Math.min(1, 480 / video.videoWidth);
                                canvas.width = Math.round(video.videoWidth * escala);
                                canvas.height = Math.round(video.videoHeight * escala);
                                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                                const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                const c = window.jsQR ? jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' }) : null;
                                texto = c ? c.data : null;
                            }
                        }
                        if (texto) { parar(); abrir(texto); return; }
                        setTimeout(ler, 150);
                    };
                    ler();
                } catch (e) {
                    botao.disabled = false;
                    botao.textContent = 'Abrir câmera';
                    mostrar(e && e.name === 'NotAllowedError'
                        ? 'Permissão da câmera negada. Toque no cadeado ao lado do endereço, libere a Câmera e tente de novo.'
                        : 'Não foi possível abrir a câmera (' + (e && e.name ? e.name : 'erro') + '). Digite o patrimônio ou a placa abaixo.');
                }
            });
        })();
    </script>
@endsection

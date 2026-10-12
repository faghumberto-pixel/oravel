@extends('layouts.colaborador')
@section('titulo', 'Ler QR - Oravel Colaborador')
@section('conteudo')
    <a href="{{ route('colab.home') }}" class="text-sm font-semibold text-zinc-400">← Início</a>
    <h1 class="mt-3 text-xl font-bold">Ler QR do equipamento</h1>

    <div x-data="leitorQr('{{ route('colab.dossie') }}')" class="mt-4">
        <video x-ref="video" playsinline muted class="aspect-square w-full rounded-2xl bg-black object-cover" x-show="ativo"></video>
        <button x-show="!ativo && suportado" @click="iniciar()" type="button" class="min-h-[3rem] w-full rounded-xl bg-orange-500 text-base font-bold text-white">Abrir câmera</button>
        <p x-show="!suportado" class="rounded-lg bg-zinc-900 p-3 text-sm text-zinc-300">Este celular não lê QR por aqui. Use a câmera do celular ou digite o patrimônio abaixo.</p>
        <p x-text="erro" x-show="erro" class="mt-2 text-sm text-red-400"></p>

        <form method="GET" action="{{ route('colab.dossie') }}" class="mt-6" onsubmit="event.preventDefault(); const v = this.q.value.trim(); if (v) window.location = this.action + '?busca=' + encodeURIComponent(v);">
            <label class="mb-1 block text-xs font-semibold uppercase text-zinc-400" for="q">Ou digite o patrimônio</label>
            <input id="q" name="q" class="min-h-[3rem] w-full rounded-xl border border-zinc-700 bg-zinc-900 px-4 text-base text-white" autocomplete="off">
            <button type="submit" class="mt-3 min-h-[3rem] w-full rounded-xl bg-zinc-800 text-base font-bold text-white">Buscar</button>
        </form>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('leitorQr', (base) => ({
                ativo: false, erro: '', stream: null,
                suportado: 'BarcodeDetector' in window && !!navigator.mediaDevices?.getUserMedia,
                async iniciar() {
                    try {
                        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                        this.$refs.video.srcObject = this.stream;
                        await this.$refs.video.play();
                        this.ativo = true;
                        const detector = new BarcodeDetector({ formats: ['qr_code'] });
                        const ler = async () => {
                            if (!this.ativo) return;
                            const [codigo] = await detector.detect(this.$refs.video).catch(() => []);
                            if (codigo) { this.parar(); this.abrir(codigo.rawValue); return; }
                            requestAnimationFrame(ler);
                        };
                        ler();
                    } catch (e) { this.erro = 'Não foi possível abrir a câmera. Libere a permissão e tente de novo.'; }
                },
                parar() { this.ativo = false; this.stream?.getTracks().forEach(t => t.stop()); },
                abrir(texto) {
                    // A etiqueta traz o link do dossiê (.../dossie-mobile/{id}) ou só o patrimônio.
                    const m = texto.match(/dossie-mobile\/([0-9a-f-]{8,})/i);
                    window.location = m ? base + '/' + m[1] : base + '?busca=' + encodeURIComponent(texto);
                },
            }));
        });
    </script>
@endsection

/**
 * Leitor de QR por camera para os apps de campo (usado por <x-mobile.leitor-qr>).
 * Mesma tecnica de /colaborador/escanear: leitor nativo (BarcodeDetector) quando
 * existe; senao o jsQR (public/vendor/jsqr.js, carregado so' quando precisa).
 * Funcao GLOBAL de proposito: o x-data chama leitorQr('metodoDoLivewire') e o app.js
 * importa uma copia propria do Alpine, entao Alpine.data() nao alcancaria o Alpine do Livewire.
 */
window.leitorQr = function (metodo) {
    return {
        aberto: false,
        aviso: '',
        stream: null,
        ativo: false,
        detector: null,

        carregarJsQr() {
            if (window.jsQR) return Promise.resolve();
            return new Promise((ok) => {
                const s = document.createElement('script');
                s.src = '/vendor/jsqr.js';
                s.onload = ok;
                s.onerror = ok;
                document.head.appendChild(s);
            });
        },

        async abrir() {
            this.aviso = '';
            if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)) {
                this.aviso = window.isSecureContext
                    ? 'Este navegador não liberou a câmera. Digite o patrimônio ou o nome abaixo.'
                    : 'A câmera só funciona em página segura (https). Digite o patrimônio ou o nome abaixo.';
                return;
            }
            try {
                await this.carregarJsQr();
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                const video = this.$refs.video;
                video.srcObject = this.stream;
                await video.play();
                this.aberto = true;
                this.ativo = true;
                this.aviso = 'Aponte a câmera para o QR da etiqueta do equipamento.';
                try { this.detector = 'BarcodeDetector' in window ? new BarcodeDetector({ formats: ['qr_code'] }) : null; } catch (e) { this.detector = null; }
                this.ler();
            } catch (e) {
                this.parar();
                this.aviso = 'Não foi possível abrir a câmera. Libere a permissão ou digite o patrimônio ou o nome abaixo.';
            }
        },

        async ler() {
            const video = this.$refs.video;
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            const passo = async () => {
                if (!this.ativo) return;
                let texto = null;
                if (video.videoWidth) {
                    if (this.detector) {
                        try { const r = await this.detector.detect(video); texto = r.length ? r[0].rawValue : null; } catch (e) { this.detector = null; }
                    }
                    if (!this.detector && texto === null && window.jsQR) {
                        const escala = Math.min(1, 480 / video.videoWidth);
                        canvas.width = Math.round(video.videoWidth * escala);
                        canvas.height = Math.round(video.videoHeight * escala);
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                        const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        const c = window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
                        texto = c ? c.data : null;
                    }
                }
                if (texto) {
                    this.parar();
                    this.$wire[metodo](texto);
                    return;
                }
                setTimeout(passo, 250);
            };
            passo();
        },

        parar() {
            this.ativo = false;
            this.aberto = false;
            if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
            this.stream = null;
        },
    };
};

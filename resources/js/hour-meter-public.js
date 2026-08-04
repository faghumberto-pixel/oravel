/**
 * Registro de horímetro via link público (sem login) -- funcionário do
 * cliente que alugou o equipamento. Mais simples que hour-meter-offline.js:
 * um único ativo fixo (vindo do servidor via token na URL), sem busca, sem
 * preload de lista, e com o nome de quem registra sendo digitado na hora
 * (não há usuário autenticado pra identificar isso).
 */
import Alpine from 'alpinejs';

window.Alpine = Alpine;

const MAX_PHOTO_SIDE = 1280;

async function compressPhotoToDataUrl(file) {
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

    const scale = Math.min(1, MAX_PHOTO_SIDE / Math.max(img.width, img.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(img.width * scale);
    canvas.height = Math.round(img.height * scale);
    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);

    return canvas.toDataURL('image/jpeg', 0.72);
}

function dataUrlToBlob(dataUrl) {
    const [meta, base64] = dataUrl.split(',');
    const mime = meta.match(/:(.*?);/)[1];
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }

    return new Blob([bytes], { type: mime });
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

window.hourMeterPublic = function (token, lastReading) {
    return {
        token,
        lastReading,

        name: localStorage.getItem('oravel:hour-meter-public:name') || '',
        reading: '',
        notes: '',
        resetConfirmed: false,
        photoDataUrl: null,
        recordedAtLabel: '',

        submitting: false,
        saved: false,
        error: null,

        get difference() {
            if (this.lastReading === null || this.lastReading === undefined || this.reading === '' || isNaN(parseFloat(this.reading))) {
                return null;
            }

            return (parseFloat(this.reading) - parseFloat(this.lastReading)).toFixed(2);
        },

        get needsResetConfirm() {
            if (this.lastReading === null || this.lastReading === undefined || this.reading === '' || isNaN(parseFloat(this.reading))) {
                return false;
            }

            const atual = parseFloat(this.reading);
            const anterior = parseFloat(this.lastReading);
            const threshold = 500;

            return atual < anterior || (atual - anterior) > threshold;
        },

        init() {
            this.recordedAtLabel = new Date().toLocaleString('pt-BR');
        },

        async onPhotoSelected(event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;

            try {
                this.photoDataUrl = await compressPhotoToDataUrl(file);
            } catch (e) {
                this.photoDataUrl = null;
            }
        },

        removePhoto() {
            this.photoDataUrl = null;
        },

        async submit() {
            this.error = null;

            if (!this.name || this.name.trim().length < 3) {
                this.error = 'Informe seu nome completo.';
                return;
            }

            if (this.reading === '' || isNaN(parseFloat(this.reading)) || parseFloat(this.reading) < 0) {
                this.error = 'Informe um horímetro válido.';
                return;
            }

            this.submitting = true;
            localStorage.setItem('oravel:hour-meter-public:name', this.name.trim());

            let latitude = null;
            let longitude = null;

            if (navigator.geolocation) {
                try {
                    const position = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 4000 });
                    });
                    latitude = position.coords.latitude;
                    longitude = position.coords.longitude;
                } catch (e) {
                    // GPS é opcional -- segue sem coordenadas.
                }
            }

            try {
                const formData = new FormData();
                formData.append('recorded_by_name', this.name.trim());
                formData.append('reading', this.reading);
                formData.append('recorded_at', new Date().toISOString());
                if (latitude !== null) formData.append('latitude', latitude);
                if (longitude !== null) formData.append('longitude', longitude);
                if (this.notes) formData.append('notes', this.notes);
                formData.append('reset_confirmed', this.resetConfirmed ? '1' : '0');
                if (this.photoDataUrl) {
                    formData.append('photo', dataUrlToBlob(this.photoDataUrl), 'horimetro.jpg');
                }

                const response = await fetch(`/hour-meter/publico/${this.token}`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: formData,
                });

                if (!response.ok) {
                    const data = await response.json().catch(() => null);
                    const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
                    this.error = firstError || data?.message || 'Não foi possível registrar. Tente novamente.';
                    return;
                }

                const data = await response.json();
                this.lastReading = parseFloat(this.reading);
                this.saved = true;
                this.reading = '';
                this.notes = '';
                this.resetConfirmed = false;
                this.photoDataUrl = null;
                this.recordedAtLabel = new Date().toLocaleString('pt-BR');
            } catch (e) {
                this.error = 'Sem conexão com o servidor. Verifique sua internet e tente novamente.';
            } finally {
                this.submitting = false;
            }
        },
    };
};

Alpine.start();

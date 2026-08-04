/**
 * Registro de horímetro offline-first (sem Livewire, sem depender do
 * Service Worker/Dexie existentes -- ver decisão em memória do projeto:
 * aquela infraestrutura ficou incompleta, syncPendingData() nunca foi
 * implementado e nenhuma tela real a usa ainda). Fila própria e isolada
 * em localStorage, sincronizada via fetch quando a rede volta.
 *
 * Página standalone (não Livewire/Filament) -- por isso inicia o próprio
 * Alpine aqui (em app.js o Alpine.start() fica comentado de propósito
 * porque Livewire/Filament já chamam isso lá).
 */
import Alpine from 'alpinejs';

window.Alpine = Alpine;

const STORAGE_KEY = 'oravel:hour-meter:queue';
const ASSETS_CACHE_KEY = 'oravel:hour-meter:assets';
const MAX_PHOTO_SIDE = 1280;

function readQueue() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
    } catch (e) {
        return [];
    }
}

function writeQueue(queue) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(queue));
}

function readCachedAssets() {
    try {
        return JSON.parse(localStorage.getItem(ASSETS_CACHE_KEY)) || null;
    } catch (e) {
        return null;
    }
}

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

/**
 * Alpine component -- x-data="hourMeterOffline()" na view.
 */
window.hourMeterOffline = function () {
    return {
        // Busca/seleção de equipamento
        assets: [],
        query: '',
        searchResults: [],
        asset: null,

        // Formulário
        reading: '',
        notes: '',
        resetConfirmed: false,
        photoDataUrl: null,
        recordedAtLabel: '',

        // Estado de sync
        queue: [],
        isOnline: navigator.onLine,
        syncing: false,
        saved: false,

        get lastReading() {
            return this.asset ? this.asset.last_reading : null;
        },

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

        get pendingCount() {
            return this.queue.filter((item) => item.status !== 'synced').length;
        },

        async init() {
            this.queue = readQueue();
            this.recordedAtLabel = new Date().toLocaleString('pt-BR');

            const cached = readCachedAssets();
            if (cached) {
                this.assets = cached;
            }

            window.addEventListener('online', () => {
                this.isOnline = true;
                this.syncPending();
            });
            window.addEventListener('offline', () => {
                this.isOnline = false;
            });

            if (this.isOnline) {
                await this.preloadAssets();
                await this.syncPending();
            }
        },

        async preloadAssets() {
            try {
                const response = await fetch('/api/v1/hour-meters/preload', {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) return;

                const data = await response.json();
                this.assets = data.assets;
                localStorage.setItem(ASSETS_CACHE_KEY, JSON.stringify(data.assets));
            } catch (e) {
                // Sem rede de verdade apesar do navigator.onLine -- segue com cache local.
            }
        },

        search() {
            const term = this.query.trim().toLowerCase();
            if (!term) {
                this.searchResults = [];
                return;
            }

            this.searchResults = this.assets.filter((a) =>
                (a.name || '').toLowerCase().includes(term)
                || (a.patrimonio || '').toLowerCase().includes(term)
                || (a.tag || '').toLowerCase().includes(term)
            ).slice(0, 20);
        },

        selectAsset(assetId) {
            this.asset = this.assets.find((a) => a.id === assetId) || null;
            this.searchResults = [];
            this.query = '';
            this.resetForm();
        },

        clearAsset() {
            this.asset = null;
            this.resetForm();
        },

        resetForm() {
            this.reading = '';
            this.notes = '';
            this.resetConfirmed = false;
            this.photoDataUrl = null;
            this.saved = false;
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

        /**
         * Salva localmente e tenta sincronizar na hora se online -- nunca
         * bloqueia a UI esperando rede, e sempre grava a fila primeiro
         * (mesmo padrão do resto do app: salvar local nunca falha por
         * causa da conexão).
         */
        async save() {
            if (!this.asset) return;

            if (this.reading === '' || isNaN(parseFloat(this.reading)) || parseFloat(this.reading) < 0) {
                alert('Informe um horímetro válido.');
                return;
            }

            const entry = {
                client_uuid: crypto.randomUUID(),
                asset_id: this.asset.id,
                asset_name: this.asset.name,
                reading: parseFloat(this.reading),
                recorded_at: new Date().toISOString(),
                latitude: null,
                longitude: null,
                notes: this.notes || null,
                reset_confirmed: this.resetConfirmed,
                photo_data_url: this.photoDataUrl,
                status: 'pending',
                error: null,
            };

            if (navigator.geolocation) {
                try {
                    const position = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 4000 });
                    });
                    entry.latitude = position.coords.latitude;
                    entry.longitude = position.coords.longitude;
                } catch (e) {
                    // GPS é opcional -- segue sem coordenadas.
                }
            }

            this.queue.push(entry);
            writeQueue(this.queue);

            // Atualiza o "último horímetro" localmente pra próxima leitura
            // deste equipamento já comparar contra o valor recém-digitado.
            this.asset.last_reading = entry.reading;
            const cached = readCachedAssets();
            if (cached) {
                const idx = cached.findIndex((a) => a.id === this.asset.id);
                if (idx >= 0) {
                    cached[idx].last_reading = entry.reading;
                    localStorage.setItem(ASSETS_CACHE_KEY, JSON.stringify(cached));
                }
            }

            this.saved = true;
            this.resetForm();

            if (this.isOnline) {
                await this.syncPending();
            }
        },

        async syncPending() {
            const pending = this.queue.filter((item) => item.status === 'pending' || item.status === 'failed');
            if (pending.length === 0 || this.syncing) return;

            this.syncing = true;

            try {
                const formData = new FormData();

                pending.forEach((entry, i) => {
                    formData.append(`readings[${i}][client_uuid]`, entry.client_uuid);
                    formData.append(`readings[${i}][asset_id]`, entry.asset_id);
                    formData.append(`readings[${i}][reading]`, entry.reading);
                    formData.append(`readings[${i}][recorded_at]`, entry.recorded_at);
                    if (entry.latitude !== null) formData.append(`readings[${i}][latitude]`, entry.latitude);
                    if (entry.longitude !== null) formData.append(`readings[${i}][longitude]`, entry.longitude);
                    if (entry.notes) formData.append(`readings[${i}][notes]`, entry.notes);
                    formData.append(`readings[${i}][reset_confirmed]`, entry.reset_confirmed ? '1' : '0');
                    if (entry.photo_data_url) {
                        formData.append(`readings[${i}][photo]`, dataUrlToBlob(entry.photo_data_url), 'horimetro.jpg');
                    }
                });

                const response = await fetch('/api/v1/hour-meters/sync', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: formData,
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();

                data.results.forEach((result) => {
                    const item = this.queue.find((q) => q.client_uuid === result.client_uuid);
                    if (!item) return;

                    item.status = result.status;
                    item.error = result.error;
                });

                // Itens sincronizados com sucesso não precisam mais ficar na
                // fila local (o registro definitivo já está no servidor).
                this.queue = this.queue.filter((item) => item.status !== 'synced');
                writeQueue(this.queue);
            } catch (e) {
                // Sem rede de verdade (fetch falhou) -- mantém tudo como
                // 'pending', tenta de novo no próximo evento 'online'.
            } finally {
                this.syncing = false;
            }
        },
    };
};

Alpine.start();

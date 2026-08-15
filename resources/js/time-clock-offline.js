/**
 * Ponto eletrônico offline-first -- mesmo padrão de hour-meter-offline.js:
 * sem Service Worker, sem Livewire, fila própria em localStorage,
 * sincronizada via fetch quando a rede volta.
 */
import Alpine from 'alpinejs';

window.Alpine = Alpine;

const STORAGE_KEY = 'oravel:time-clock:queue';

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

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Alpine component -- x-data="timeClockOffline()" na view.
 */
window.timeClockOffline = function (employeeId) {
    return {
        employeeId,
        queue: [],
        isOnline: navigator.onLine,
        syncing: false,
        saved: false,
        savedTipo: null,

        get pendingCount() {
            return this.queue.filter((item) => item.status !== 'synced').length;
        },

        get lastRegisteredTipo() {
            const synced = this.queue.filter((item) => item.status === 'synced' || item.status === 'pending');
            if (synced.length === 0) return null;

            return synced[synced.length - 1].tipo;
        },

        async init() {
            this.queue = readQueue();

            window.addEventListener('online', () => {
                this.isOnline = true;
                this.syncPending();
            });
            window.addEventListener('offline', () => {
                this.isOnline = false;
            });

            if (this.isOnline) {
                await this.syncPending();
            }
        },

        /**
         * Salva localmente primeiro (nunca bloqueia a UI esperando rede) e
         * tenta sincronizar na hora se online -- mesmo padrão do
         * apontamento de horímetro.
         */
        async register(tipo) {
            const entry = {
                client_uuid: crypto.randomUUID(),
                employee_id: this.employeeId,
                tipo,
                device_recorded_at: new Date().toISOString(),
                latitude: null,
                longitude: null,
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

            this.saved = true;
            this.savedTipo = tipo;

            if (this.isOnline) {
                await this.syncPending();
            }
        },

        async syncPending() {
            const pending = this.queue.filter((item) => item.status === 'pending' || item.status === 'failed');
            if (pending.length === 0 || this.syncing) return;

            this.syncing = true;

            try {
                const response = await fetch('/api/v1/time-clocks/sync', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ batidas: pending }),
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

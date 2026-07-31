/**
 * Inicialização do app offline-first para técnico de campo
 * 1. Registra o Service Worker
 * 2. Pré-carrega dados do dia se online
 * 3. Monitora estado de conexão
 */

import { db, preloadDailyData, checkConnection } from './idb';

let isOnline = navigator.onLine;

/**
 * Registra o Service Worker
 */
export async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        console.log('[App] Service Worker não suportado neste navegador');
        return false;
    }

    try {
        const registration = await navigator.serviceWorker.register(
            '/service-worker.js',
            { scope: '/' }
        );
        console.log('[App] Service Worker registrado:', registration.scope);

        // Listener para atualizações do SW
        registration.addEventListener('updatefound', () => {
            const newWorker = registration.installing;
            console.log('[App] Service Worker atualizado');

            newWorker.addEventListener('statechange', () => {
                if (newWorker.state === 'activated') {
                    // Avisar usuário que nova versão está disponível
                    window.dispatchEvent(new CustomEvent('sw-updated'));
                }
            });
        });

        return true;
    } catch (error) {
        console.error('[App] Erro ao registrar Service Worker:', error);
        return false;
    }
}

/**
 * Inicializa o banco de dados e pré-carrega dados
 */
export async function initializeOfflineDB() {
    try {
        console.log('[App] Inicializando banco de dados offline...');

        // Se online: pré-carrega dados
        if (isOnline) {
            const success = await preloadDailyData();
            if (success) {
                console.log('[App] Dados do dia pré-carregados com sucesso');
                window.dispatchEvent(new CustomEvent('data-preloaded'));
            } else {
                console.warn('[App] Falha ao pré-carregar dados, mas continuando em modo offline');
            }
        } else {
            console.log('[App] Offline: pulando pré-carregamento');
        }

        return true;
    } catch (error) {
        console.error('[App] Erro ao inicializar banco:', error);
        return false;
    }
}

/**
 * Monitora mudanças de conectividade
 */
export function monitorConnection() {
    window.addEventListener('online', () => {
        console.log('[App] 🟢 Online detectado');
        isOnline = true;
        window.dispatchEvent(new CustomEvent('app-online'));

        // Tentar sincronização em background
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            navigator.serviceWorker.ready.then(registration => {
                registration.sync.register('sync-wizard-data').then(() => {
                    console.log('[App] Background Sync acionado');
                }).catch(err => {
                    console.log('[App] Background Sync indisponível:', err);
                });
            });
        }
    });

    window.addEventListener('offline', () => {
        console.log('[App] 🔴 Offline detectado');
        isOnline = false;
        window.dispatchEvent(new CustomEvent('app-offline'));
    });

    // Monitoramento periódico (fallback para navegadores sem eventos online/offline)
    setInterval(async () => {
        const wasOnline = isOnline;
        isOnline = await checkConnection();

        if (wasOnline && !isOnline) {
            console.log('[App] Conexão perdida (detecção periódica)');
            window.dispatchEvent(new CustomEvent('app-offline'));
        } else if (!wasOnline && isOnline) {
            console.log('[App] Conexão restaurada (detecção periódica)');
            window.dispatchEvent(new CustomEvent('app-online'));
        }
    }, 30000); // A cada 30 segundos
}

/**
 * Retorna estado de conexão atual
 */
export function getConnectionStatus() {
    return isOnline;
}

/**
 * Inicialização principal
 */
export async function initializeApp() {
    console.log('[App] 🚀 Inicializando Oravel Técnico de Campo');

    // 1. Registrar Service Worker
    await registerServiceWorker();

    // 2. Inicializar banco de dados e pré-carregar dados
    await initializeOfflineDB();

    // 3. Monitorar conexão
    monitorConnection();

    // 4. Notificar que app está pronto
    window.dispatchEvent(new CustomEvent('app-ready'));
    console.log('[App] ✅ Inicialização concluída');
}

// Auto-initialize quando o app carrega
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeApp);
} else {
    initializeApp();
}

export default {
    registerServiceWorker,
    initializeOfflineDB,
    monitorConnection,
    getConnectionStatus,
    initializeApp,
};

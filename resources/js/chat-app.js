/**
 * Entrada Vite dedicada ao chat standalone (/chat) -- só o essencial:
 * registro do Service Worker (escopo restrito a /chat, pra não interferir
 * com o registro em scope '/' usado pelas páginas mobile de campo) e
 * notificação de mensagem nova (som + título da aba + Notification API).
 *
 * Deliberadamente NÃO importa offline/init.js inteiro -- initializeApp()
 * carrega pré-carregamento de IndexedDB e monitoramento de conexão do
 * wizard offline de campo, que não fazem sentido aqui.
 */
import '../css/app.css';
import { enqueueMessage, pendingCount, watchConnectionAndSync } from './chat/offline';

if ('serviceWorker' in navigator) {
    navigator.serviceWorker
        .register('/service-worker.js', { scope: '/chat' })
        .catch((error) => console.error('[Chat] Falha ao registrar Service Worker:', error));
}

// Exposto em window pro Alpine (chatComponent(), inline em
// global-chat.blade.php) chamar sem precisar de um bundler por componente.
window.OravelChatOffline = { enqueueMessage, pendingCount };

/**
 * Beep curto e sutil via Web Audio API -- sem depender de arquivo de
 * áudio externo (nada pra hospedar, nada que possa retornar 404).
 */
function playNotificationSound() {
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        const ctx = new AudioContextClass();
        const oscillator = ctx.createOscillator();
        const gain = ctx.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(880, ctx.currentTime);
        oscillator.frequency.setValueAtTime(660, ctx.currentTime + 0.09);

        gain.gain.setValueAtTime(0.001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.15, ctx.currentTime + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.22);

        oscillator.connect(gain);
        gain.connect(ctx.destination);

        oscillator.start(ctx.currentTime);
        oscillator.stop(ctx.currentTime + 0.22);
        oscillator.onended = () => ctx.close();
    } catch (error) {
        // Autoplay bloqueado antes de qualquer interação do usuário, ou
        // navegador sem suporte -- não é crítico, só não toca o som.
    }
}

/**
 * Pede permissão de notificação assim que possível (deve ser chamado
 * após alguma interação do usuário, ex. o próprio carregamento da tela
 * de chat já autenticada conta como contexto suficiente na maioria dos
 * navegadores mobile -- se o browser recusar silenciosamente, o app
 * segue funcionando sem notificação de tela, só com som/badge).
 */
function ensureNotificationPermission() {
    if (!('Notification' in window)) return;
    if (Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

/**
 * Dispara notificação do sistema (aparece mesmo com a aba em segundo
 * plano) + atualiza o badge do ícone (Badging API, suportado em Chrome/
 * Edge desktop e Android; iOS Safari ainda não suporta badge nativo de
 * PWA em 2026, mas a chamada é no-op segura nesse caso).
 */
function notifyNewMessage(senderName, preview, unreadTotal) {
    playNotificationSound();

    if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
        try {
            const notification = new Notification(senderName || 'Nova mensagem', {
                body: preview || 'Você recebeu uma nova mensagem no Oravel Chat.',
                icon: '/icon.png',
                badge: '/icon.png',
                tag: 'oravel-chat-message',
            });
            notification.onclick = () => {
                window.focus();
                notification.close();
            };
        } catch (error) {
            // ignora falha silenciosa de notificação
        }
    }

    if ('setAppBadge' in navigator) {
        if (unreadTotal > 0) {
            navigator.setAppBadge(unreadTotal).catch(() => {});
        } else {
            navigator.clearAppBadge().catch(() => {});
        }
    }

    document.title = unreadTotal > 0
        ? `(${unreadTotal}) Oravel Chat`
        : 'Oravel Chat';
}

document.addEventListener('DOMContentLoaded', () => {
    ensureNotificationPermission();

    watchConnectionAndSync((syncedCount) => {
        window.dispatchEvent(new CustomEvent('chat-offline-synced', { detail: { syncedCount } }));
    });

    // Escuta o evento 'chat-new-message' disparado pelo componente Livewire
    // GlobalChat sempre que o poll encontrar mensagens novas não vistas
    // ainda por este cliente (ver resources/views/livewire/global-chat.blade.php
    // e app/Livewire/GlobalChat.php).
    window.addEventListener('chat-new-message', (event) => {
        const { senderName, preview, unreadTotal } = event.detail ?? {};
        notifyNewMessage(senderName, preview, unreadTotal ?? 0);
    });

    // Ao voltar o foco pra aba, zera o título/badge -- as mensagens já
    // deveriam ter sido marcadas como lidas pelo próprio componente
    // Livewire ao renderizar a conversa aberta.
    window.addEventListener('focus', () => {
        document.title = 'Oravel Chat';
        if ('clearAppBadge' in navigator) {
            navigator.clearAppBadge().catch(() => {});
        }
    });
});

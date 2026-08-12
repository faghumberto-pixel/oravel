/**
 * Fila offline de mensagens de texto do chat -- "mesma lógica da OS":
 * escreve localmente quando sem rede, sincroniza automaticamente ao
 * voltar a conexão. Banco Dexie próprio (OravelChatDB), separado do
 * banco usado pelo wizard de campo (OravelTechnicianDB em offline/idb.js)
 * para não acoplar os dois domínios.
 *
 * Escopo: só mensagens de texto. Imagem/áudio/documento exigem upload
 * multipart de blob binário -- não há fila confiável pra isso sem
 * infraestrutura adicional (fora do pedido original).
 */
import Dexie from 'dexie';

const db = new Dexie('OravelChatDB');

db.version(1).stores({
    // ++id: chave local autoincrementada. client_id: usado pro servidor
    // deduplicar caso a mesma entrada seja sincronizada mais de uma vez.
    outbox: '++id, client_id, synced',
});

/**
 * Gera um id de cliente razoavelmente único sem depender de crypto.randomUUID
 * (indisponível em contexto não-seguro/HTTP puro em alguns navegadores mobile
 * mais antigos).
 */
function generateClientId() {
    return `c${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

/**
 * Enfileira uma mensagem de texto localmente. Chamado quando o envio via
 * Livewire falhar por falta de rede (ver hook no chatComponent() em
 * global-chat.blade.php).
 */
export async function enqueueMessage(recipientId, message) {
    const clientId = generateClientId();

    await db.outbox.add({
        client_id: clientId,
        recipient_id: recipientId,
        message,
        created_at: new Date(),
        synced: false,
    });

    return clientId;
}

/**
 * Quantas mensagens ainda não foram sincronizadas -- usado pra mostrar
 * indicador visual ("2 mensagens aguardando conexão").
 */
export async function pendingCount() {
    return db.outbox.where('synced').equals(false).count();
}

/**
 * Tenta sincronizar tudo que está na fila, em ordem de criação. Para no
 * primeiro erro de rede (mantém a ordem e evita marcar como enviada uma
 * mensagem que na verdade falhou) mas segue tentando as próximas se o
 * erro for de validação do servidor (não adianta re-tentar infinitamente
 * algo que o servidor rejeitou).
 */
export async function syncPendingMessages() {
    const pending = await db.outbox.where('synced').equals(false).sortBy('created_at');

    let syncedCount = 0;

    for (const item of pending) {
        try {
            const response = await fetch('/chat/messages/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({
                    recipient_id: item.recipient_id,
                    message: item.message,
                    client_id: item.client_id,
                }),
            });

            if (response.status === 419 || response.status === 401) {
                // Sessão expirada/CSRF token velho -- para de tentar, o
                // usuário precisa recarregar/logar de novo. Não é erro de
                // conteúdo, não descarta a mensagem da fila.
                break;
            }

            if (!response.ok) {
                // Erro do servidor (validação, etc.) -- descarta da fila
                // pra não tentar pra sempre algo que nunca vai passar, mas
                // segue tentando as próximas mensagens.
                await db.outbox.update(item.id, { synced: true, failed: true });
                continue;
            }

            await db.outbox.update(item.id, { synced: true });
            syncedCount++;
        } catch (error) {
            // Falha de rede real (ainda offline) -- para aqui, tenta de
            // novo no próximo evento 'online' ou próximo poll.
            break;
        }
    }

    return syncedCount;
}

/**
 * Observa o evento 'online' do navegador e sincroniza automaticamente.
 * Também tenta uma vez ao carregar a página, caso a fila já tivesse itens
 * de uma sessão offline anterior.
 */
export function watchConnectionAndSync(onSynced) {
    const trySync = async () => {
        if (!navigator.onLine) return;

        const synced = await syncPendingMessages();
        if (synced > 0 && typeof onSynced === 'function') {
            onSynced(synced);
        }
    };

    window.addEventListener('online', trySync);
    trySync();

    // Fallback: tenta a cada 20s enquanto a página estiver aberta, caso o
    // evento 'online' não dispare corretamente (acontece em alguns Android
    // com Wi-Fi instável).
    setInterval(trySync, 20000);
}

export { db as chatOfflineDb };

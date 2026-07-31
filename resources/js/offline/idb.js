/**
 * IndexedDB setup com Dexie.js
 * Banco local para pré-carregamento de dados e rascunhos offline
 */

import Dexie from 'dexie';

export const db = new Dexie('OravelTechnicianDB');

// Schema: versão 1
db.version(1).stores({
    // Dados pré-carregados do servidor (cache read-only)
    technician_tasks: 'id',           // MaintenanceOrder + AssetMovement
    assets: 'id',                     // Asset (completo com criticality, group, capacity)
    contracts: 'id',                  // Contract
    checklist_templates: 'id',        // Checklist templates
    asset_movement_history: 'id',     // Histórico de mobilizações/desmobilizações

    // Dados locais (rascunhos e edições offline)
    wizard_drafts: 'id, type, task_id',        // { id, type: 'maintenance|mobilization', task_id, data: {...}, last_save }
    replacement_requests: 'id',       // { id, maintenance_order_id, asset_id, urgency, reason, created_at, synced }
    sync_queue: '++id, task_id, action, synced', // { id, action, table, task_id, data, created_at, synced }
    offline_photos: 'id',             // { id, task_id, type: 'damage|equipment', blob, mime_type, metadata }
    offline_audio: 'id',              // { id, task_id, blob, mime_type, is_transcribed, transcription }

    // Cache de estado de sincronização
    sync_status: 'id'                 // { id, task_id, status: 'pending|syncing|synced|conflict', last_attempt, error }
});

/**
 * Pré-carrega dados para o dia ao iniciar (se online)
 * Chamado ao abrir o app com conexão de rede
 */
export async function preloadDailyData() {
    try {
        console.log('[IDB] Pré-carregando dados do dia...');

        // Fetch da API
        const response = await fetch('/api/technician/tasks-of-day');
        if (!response.ok) throw new Error(`API error: ${response.status}`);

        const data = await response.json();

        // Armazenar localmente
        await db.transaction('rw', db.technician_tasks, db.assets, db.contracts, db.checklist_templates, async () => {
            if (data.tasks) await db.technician_tasks.bulkPut(data.tasks);
            if (data.assets) await db.assets.bulkPut(data.assets);
            if (data.contracts) await db.contracts.bulkPut(data.contracts);
            if (data.checklist_templates) await db.checklist_templates.bulkPut(data.checklist_templates);
        });

        console.log('[IDB] Pré-carregamento concluído');
        return true;
    } catch (error) {
        console.error('[IDB] Pré-carregamento falhou:', error);
        return false;
    }
}

/**
 * Salva rascunho localmente (offline)
 */
export async function saveDraft(type, taskId, data) {
    try {
        await db.wizard_drafts.put({
            id: `${type}-${taskId}`,
            type,
            task_id: taskId,
            data,
            last_save: new Date(),
        });
        console.log('[IDB] Rascunho salvo:', type, taskId);
        return true;
    } catch (error) {
        console.error('[IDB] Erro ao salvar rascunho:', error);
        return false;
    }
}

/**
 * Carrega rascunho (se existir)
 */
export async function loadDraft(type, taskId) {
    try {
        const draft = await db.wizard_drafts.get(`${type}-${taskId}`);
        return draft?.data || null;
    } catch (error) {
        console.error('[IDB] Erro ao carregar rascunho:', error);
        return null;
    }
}

/**
 * Adiciona ação à fila de sincronização
 */
export async function queueSyncAction(action, table, taskId, data) {
    try {
        await db.sync_queue.add({
            action,
            table,
            task_id: taskId,
            data,
            created_at: new Date(),
            synced: false,
        });
        console.log('[IDB] Ação enfileirada para sync:', action, table);
        return true;
    } catch (error) {
        console.error('[IDB] Erro ao enfileirar sincronização:', error);
        return false;
    }
}

/**
 * Obtém fila de sincronização (ações pendentes)
 */
export async function getSyncQueue() {
    try {
        return await db.sync_queue.where('synced').equals(false).toArray();
    } catch (error) {
        console.error('[IDB] Erro ao obter fila de sync:', error);
        return [];
    }
}

/**
 * Marca ação como sincronizada
 */
export async function markSynced(queueId) {
    try {
        await db.sync_queue.update(queueId, { synced: true });
        console.log('[IDB] Ação marcada como sincronizada:', queueId);
    } catch (error) {
        console.error('[IDB] Erro ao marcar sincronização:', error);
    }
}

/**
 * Armazena foto capturada (offline)
 */
export async function saveOfflinePhoto(taskId, type, blob, metadata = {}) {
    try {
        const id = `${taskId}-${type}-${Date.now()}`;
        await db.offline_photos.put({
            id,
            task_id: taskId,
            type, // 'damage' | 'equipment'
            blob,
            mime_type: blob.type,
            metadata,
        });
        console.log('[IDB] Foto salva offline:', id);
        return id;
    } catch (error) {
        console.error('[IDB] Erro ao salvar foto:', error);
        return null;
    }
}

/**
 * Armazena áudio capturado (offline)
 */
export async function saveOfflineAudio(taskId, blob, metadata = {}) {
    try {
        const id = `${taskId}-audio-${Date.now()}`;
        await db.offline_audio.put({
            id,
            task_id: taskId,
            blob,
            mime_type: blob.type,
            is_transcribed: false,
            transcription: null,
            metadata,
        });
        console.log('[IDB] Áudio salvo offline:', id);
        return id;
    } catch (error) {
        console.error('[IDB] Erro ao salvar áudio:', error);
        return null;
    }
}

/**
 * Obtém fotos/áudios pendentes de upload
 */
export async function getPendingMedia(taskId = null) {
    try {
        const photos = taskId
            ? await db.offline_photos.where('task_id').equals(taskId).toArray()
            : await db.offline_photos.toArray();

        const audio = taskId
            ? await db.offline_audio.where('task_id').equals(taskId).toArray()
            : await db.offline_audio.toArray();

        return { photos, audio };
    } catch (error) {
        console.error('[IDB] Erro ao obter mídia pendente:', error);
        return { photos: [], audio: [] };
    }
}

/**
 * Limpa draft após sucesso de sincronização
 */
export async function clearDraft(type, taskId) {
    try {
        await db.wizard_drafts.delete(`${type}-${taskId}`);
        console.log('[IDB] Rascunho limpo:', type, taskId);
    } catch (error) {
        console.error('[IDB] Erro ao limpar rascunho:', error);
    }
}

/**
 * Teste de conexão (check se pode fazer fetch ao servidor)
 */
export async function checkConnection() {
    try {
        const response = await fetch('/api/health-check', { method: 'HEAD' });
        return response.ok;
    } catch (error) {
        return false;
    }
}

/**
 * Calcula tamanho total do banco (para avisar se próximo do limite)
 */
export async function getDBSize() {
    try {
        let total = 0;

        // Estimar tamanho por tabela
        const tables = [
            'technician_tasks', 'assets', 'contracts', 'checklist_templates',
            'wizard_drafts', 'sync_queue', 'offline_photos', 'offline_audio'
        ];

        for (const table of tables) {
            const items = await db[table].toArray();
            for (const item of items) {
                total += JSON.stringify(item).length;
            }
        }

        return Math.round(total / 1024); // KB
    } catch (error) {
        console.error('[IDB] Erro ao calcular tamanho:', error);
        return 0;
    }
}

/**
 * Salva solicitação de troca de equipamento (offline-first)
 * Cria registro local + enfileira para sync ao reconectar
 */
export async function saveReplacementRequest(maintenanceOrderId, assetId, urgency, reason) {
    try {
        const id = `replacement-${maintenanceOrderId}-${Date.now()}`;

        // Salvar localmente
        await db.replacement_requests.put({
            id,
            maintenance_order_id: maintenanceOrderId,
            asset_id: assetId,
            urgency, // 'critico' | 'urgente' | 'normal'
            reason,
            created_at: new Date(),
            synced: false,
        });

        // Enfileirar para sincronização
        await queueSyncAction('create', 'equipment_replacements', maintenanceOrderId, {
            maintenance_order_id: maintenanceOrderId,
            asset_id: assetId,
            urgency,
            reason,
        });

        console.log('[IDB] Solicitação de troca salva offline:', id);
        return id;
    } catch (error) {
        console.error('[IDB] Erro ao salvar solicitação de troca:', error);
        return null;
    }
}

/**
 * Obtém solicitações de troca pendentes de sincronização
 */
export async function getPendingReplacements() {
    try {
        return await db.replacement_requests.where('synced').equals(false).toArray();
    } catch (error) {
        console.error('[IDB] Erro ao obter solicitações pendentes:', error);
        return [];
    }
}

/**
 * Marca solicitação de troca como sincronizada
 */
export async function markReplacementSynced(replacementId) {
    try {
        await db.replacement_requests.update(replacementId, { synced: true });
        console.log('[IDB] Solicitação de troca marcada como sincronizada:', replacementId);
    } catch (error) {
        console.error('[IDB] Erro ao marcar solicitação sincronizada:', error);
    }
}

export default db;

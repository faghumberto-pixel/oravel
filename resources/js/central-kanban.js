import Sortable from 'sortablejs';

/**
 * Drag-and-drop do Kanban Comercial (Painel Central) -- pedido do usuario
 * 2026-07-28: a primeira versao usava HTML5 drag nativo puro (sem lib), que
 * funciona mas nao tem a sensacao "Trello/Pipedrive" pedida (sem animacao
 * suave, sem ghost/placeholder, sem bloqueio visual claro pra coluna
 * "Ganho" que nao aceita drop). SortableJS resolve isso.
 *
 * Chamado via x-init="initKanbanColumn($el, $wire, ...)" no corpo de cada
 * coluna (kanban.blade.php) -- $wire vem do Alpine (bridge Livewire), assim
 * o onEnd chama exatamente o mesmo Livewire::moveToStage() que o <select>
 * de fallback acessivel ja usa, sem duplicar logica de validacao.
 */
window.initKanbanColumn = function (el, wire, stageId, acceptsDrop) {
    Sortable.create(el, {
        group: {
            name: 'oravel-kanban-leads',
            pull: true,
            // Coluna "Ganho" e' so' leitura -- nao aceita soltar card (backend
            // ja rejeitava isso, mas aqui nem chega a soltar visualmente).
            put: acceptsDrop,
        },
        animation: 220,
        easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
        ghostClass: 'kanban-card-ghost',
        chosenClass: 'kanban-card-chosen',
        dragClass: 'kanban-card-drag',
        // Leads ja fechados (Ganho) nao tem esse atributo -- nao da' pra
        // nem comecar a arrastar eles.
        filter: '[data-closed="1"]',
        onMove(evt) {
            return evt.to.dataset.acceptsDrop !== '0';
        },
        onEnd(evt) {
            const leadId = evt.item.dataset.leadId;
            const fromStage = evt.from.dataset.stage;
            const toStage = evt.to.dataset.stage;

            if (fromStage === toStage) {
                return;
            }

            wire.moveToStage(leadId, toStage);
        },
    });
};

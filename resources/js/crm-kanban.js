import Sortable from 'sortablejs';

/**
 * Drag-and-drop do Kanban do CRM (painel admin, página Funil de Vendas).
 * Mesmo padrão de central-kanban.js: onEnd chama o mesmo Livewire
 * moverLead() que o <select> de fallback acessível usa, sem duplicar regra.
 * Soltar numa etapa "Perdido" não move direto: o backend abre o modal de motivo.
 */
window.initCrmKanbanColumn = function (el, wire) {
    Sortable.create(el, {
        group: 'oravel-crm-kanban',
        animation: 220,
        easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
        ghostClass: 'kanban-card-ghost',
        chosenClass: 'kanban-card-chosen',
        dragClass: 'kanban-card-drag',
        // Leads já encerrados (ganho/perdido) ficam travados, como no Central.
        filter: '[data-closed="1"]',
        onEnd(evt) {
            const leadId = evt.item.dataset.leadId;
            const from = evt.from.dataset.etapa;
            const to = evt.to.dataset.etapa;

            if (from === to) {
                return;
            }

            wire.moverLead(leadId, to);
        },
    });
};

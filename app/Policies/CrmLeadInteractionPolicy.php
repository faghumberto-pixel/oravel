<?php

namespace App\Policies;

/**
 * Policy dedicada apenas para o Laravel conseguir identificar o modelo em
 * checagens sem $record (viewAny/create) -- ver AbstractPolicy. Sem
 * HasSaaSMetadata proprio: e um modelo filho de CrmLead, mesmo padrao de
 * EquipmentDamageFollowUpPolicy -- a trava comercial (tabela_crm_leads) ja
 * acontece no acesso ao CrmLead/pagina de agenda antes de chegar aqui.
 */
class CrmLeadInteractionPolicy extends AbstractPolicy {}

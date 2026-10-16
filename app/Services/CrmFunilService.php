<?php

namespace App\Services;

use App\Models\CrmEtapa;
use App\Models\CrmLead;
use App\Models\CrmMotivoPerda;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Única porta de mudança de etapa de um lead (Kanban, formulário e, na Fase 5,
 * a API). Mesmas travas do antigo CrmFunil::moveStage(): vendedor só mexe no
 * que é seu, perda exige motivo (e detalhe quando o motivo pede), e as etapas
 * têm de ser do mesmo tenant do lead.
 */
class CrmFunilService
{
    /**
     * @throws ValidationException quando falta motivo/detalhe da perda
     * @throws \InvalidArgumentException quando a etapa é de outro tenant ou o usuário não pode mover o lead
     */
    public function moverLead(CrmLead $lead, CrmEtapa $etapa, ?User $usuario = null, ?string $motivoSlug = null, ?string $detalhe = null): CrmLead
    {
        if ((string) $etapa->tenant_id !== (string) $lead->tenant_id) {
            throw new \InvalidArgumentException('Etapa de outro tenant.');
        }

        if ($usuario && (string) $usuario->tenant_id !== (string) $lead->tenant_id && ! $usuario->isSuperAdmin()) {
            throw new \InvalidArgumentException('Lead de outro tenant.');
        }

        if ($usuario && ! $usuario->isSuperAdmin() && ! $usuario->canSeeAllCrmLeads() && (string) $lead->assigned_user_id !== (string) $usuario->id) {
            throw new \InvalidArgumentException('Você só pode mover leads atribuídos a você.');
        }

        $dados = ['etapa_id' => $etapa->id];

        if ($etapa->isPerdido()) {
            $motivo = $motivoSlug
                ? CrmMotivoPerda::withoutGlobalScopes()->where('tenant_id', $lead->tenant_id)->where('slug', $motivoSlug)->first()
                : null;

            if (! $motivo) {
                throw ValidationException::withMessages(['motivo' => 'Informe o motivo da perda.']);
            }

            if ($motivo->exige_detalhe && trim((string) $detalhe) === '') {
                throw ValidationException::withMessages(['detalhe' => 'Descreva o detalhe do motivo da perda.']);
            }

            $dados['lost_reason_category'] = $motivo->slug;
            $dados['lost_reason'] = $detalhe;
        }

        $lead->update($dados);

        return $lead->refresh();
    }
}

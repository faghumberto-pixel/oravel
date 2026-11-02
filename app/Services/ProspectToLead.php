<?php

namespace App\Services;

use App\Models\Prospect;
use App\Models\SalesLead;
use App\Models\SalesLeadInteraction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Converte um Prospect em SalesLead (CRM Comercial / Funil / Kanban / Programacao da Central).
 * O lead nasce em Prospeccao, com os contatos ja feitos copiados como interacoes (o funil exige ao menos uma
 * para avancar). Idempotente: um prospect so' vira lead uma vez.
 */
class ProspectToLead
{
    private const CANAIS = [
        'whatsapp' => SalesLeadInteraction::CHANNEL_WHATSAPP,
        'telefone' => SalesLeadInteraction::CHANNEL_TELEFONE,
        'email' => SalesLeadInteraction::CHANNEL_EMAIL,
        'visita' => SalesLeadInteraction::CHANNEL_VISITA,
    ];

    public static function converter(Prospect $p, User $por): SalesLead
    {
        if ($p->sales_lead_id && ($existente = SalesLead::find($p->sales_lead_id))) {
            return $existente;
        }

        return DB::transaction(function () use ($p, $por): SalesLead {
            $lead = SalesLead::create([
                'company_name' => $p->nome,
                'phone' => $p->telefone,
                'email' => $p->email,
                'city' => $p->cidade,
                'source' => SalesLead::SOURCE_PROSPECCAO_ATIVA,
                'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
                'decision_makers' => filled($p->socio) ? [['name' => $p->socio, 'role' => $p->socio_cargo ?? '']] : [],
                'assigned_user_id' => $por->id,
                'next_followup_date' => $p->proximo_contato,
            ]);

            $ficha = collect([
                'Veio de Prospects'.($p->origem ? " ({$p->origem})" : ''),
                'CNPJ '.$p->cnpj,
                $p->razao_social ? 'Razão social: '.$p->razao_social : null,
                $p->porte ? 'Porte '.$p->porte : null,
                $p->capital ? 'Capital R$ '.number_format($p->capital, 0, ',', '.') : null,
                $p->ano_abertura ? 'Aberta em '.$p->ano_abertura : null,
                $p->atividade ? 'Atividade: '.$p->atividade : null,
                filled($p->observacoes) ? 'Observações: '.$p->observacoes : null,
            ])->filter()->implode(' · ');

            $lead->interactions()->create([
                'user_id' => $por->id,
                'channel' => SalesLeadInteraction::CHANNEL_OUTRO,
                'contact_date' => now(),
                'summary' => $ficha,
                'stage_at_time' => SalesLead::STAGE_PROSPECCAO,
            ]);

            foreach ($p->contatos()->orderBy('data')->get() as $c) {
                $lead->interactions()->create([
                    'user_id' => $por->id,
                    'channel' => self::CANAIS[$c->canal] ?? SalesLeadInteraction::CHANNEL_OUTRO,
                    'contact_date' => $c->data,
                    'summary' => trim(($c->titulo ? $c->titulo.': ' : '').$c->texto),
                    'stage_at_time' => SalesLead::STAGE_PROSPECCAO,
                ]);
            }
            $lead->refreshInteractionCache();

            $p->update(['sales_lead_id' => $lead->id, 'status' => 'convertido']);
            $p->compromissos()->update(['sales_lead_id' => $lead->id]);

            return $lead;
        });
    }
}

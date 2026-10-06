<?php

namespace App\Console\Commands;

use App\Filament\Central\Resources\SalesLeadResource;
use App\Models\Client;
use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\SalesLead;
use App\Models\SalesLeadInteraction;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SiteVisitMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Move para o funil de vendas da Oravel (Central) os leads do formulário do site que foram gravados,
 * antes de 06/10/2026, como CrmLead no CRM do tenant da Oravel -- onde qualquer usuário desse tenant os
 * via. Preserva data, mensagem, e-mail e origem, descobre cidade/estado e de onde veio pela visita ativa
 * no momento do envio (SiteVisitMatcher), repõe os avisos antigos para abrir o lead novo e apaga o CrmLead
 * (e as interações dele) do tenant. Por padrão só LISTA; --apply move de verdade.
 */
class MoveSiteLeadsToCentral extends Command
{
    protected $signature = 'leads:move-site-leads-to-central {--apply : Move de verdade (sem isso só lista)}';

    protected $description = 'Move os leads do site da Oravel do CRM do tenant para o funil de vendas da Central';

    public function handle(): int
    {
        $slug = config('services.inbound.channels.site-oravel.tenant_slug', 'oravel');
        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            $this->error("Tenant '{$slug}' não encontrado.");

            return self::FAILURE;
        }

        $leads = CrmLead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('source', 'like', 'site\\_%')->orderBy('created_at')->get();
        $author = User::withoutGlobalScopes()->whereIn(DB::raw('lower(email)'), array_map('strtolower', config('oravel.super_admins', [])))->orderBy('created_at')->first();

        foreach ($leads as $lead) {
            $visit = SiteVisitMatcher::forMoment($lead->created_at);
            $place = $visit ? collect([$visit->city, $visit->state])->filter()->implode('/') : 'visitante não identificado';
            $this->line("  {$lead->created_at->format('d/m/Y H:i')} · {$lead->company_name} · {$lead->email} · {$place}");
        }

        if (! $this->option('apply')) {
            $this->info("{$leads->count()} lead(s) do site no CRM do tenant '{$slug}'. Nada movido (use --apply).");

            return self::SUCCESS;
        }

        foreach ($leads as $lead) {
            DB::transaction(fn () => $this->move($lead, $author));
        }

        $this->info("{$leads->count()} lead(s) movido(s) para o funil de vendas da Central.");

        return self::SUCCESS;
    }

    private function move(CrmLead $lead, ?User $author): void
    {
        $interactions = CrmLeadInteraction::withoutGlobalScopes()->where('crm_lead_id', $lead->id)->orderBy('created_at')->get();
        $firstSummary = (string) ($interactions->first()?->summary ?? '');
        $message = null;

        if (str_contains($firstSummary, "Mensagem do visitante:\n")) {
            $message = trim(explode("\n\nOrigem do anúncio:", trim(explode("Mensagem do visitante:\n", $firstSummary, 2)[1]))[0]);
        }

        $niche = collect(Client::nicheLabels())->search(fn ($label, $key) => mb_strtolower($label) === mb_strtolower((string) $lead->segment) || $key === $lead->segment);
        $visit = SiteVisitMatcher::forMoment($lead->created_at);
        $own = array_filter([
            'utm_source' => $lead->utm_source, 'utm_medium' => $lead->utm_medium, 'utm_campaign' => $lead->utm_campaign,
            'utm_term' => $lead->utm_term, 'utm_content' => $lead->utm_content,
            'gclid' => $lead->gclid, 'gbraid' => $lead->gbraid, 'wbraid' => $lead->wbraid,
        ], fn ($v) => filled($v));

        $details = array_filter(array_merge([
            'origem' => str_replace('site_', '', (string) $lead->source),
            'segmento_informado' => $lead->segment,
            'porte' => $lead->company_size,
            'landing_url' => $lead->landing_url,
            'recebido_em' => $lead->created_at->format('d/m/Y H:i'),
        ], $own, $visit ? SiteVisitMatcher::details($visit, $own) : []), fn ($v) => filled($v));

        $new = new SalesLead([
            'company_name' => $lead->company_name ?: $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone ?: $lead->whatsapp,
            'city' => $visit?->city,
            'uf' => $visit?->state,
            'source' => SalesLead::SOURCE_SITE,
            'segment' => $niche === false ? null : $niche,
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'decision_makers' => [['name' => $lead->name, 'role' => null]],
            'inbound_message' => $message ?: null,
            'inbound_details' => $details,
        ]);
        $new->created_at = $lead->created_at;
        $new->updated_at = $lead->created_at;
        $new->save();

        if ($author) {
            foreach ($interactions as $interaction) {
                SalesLeadInteraction::create([
                    'sales_lead_id' => $new->id,
                    'user_id' => $author->id,
                    'channel' => $interaction->channel,
                    'contact_date' => $interaction->contact_date,
                    'summary' => $interaction->summary,
                    'stage_at_time' => SalesLead::STAGE_PROSPECCAO,
                ]);
            }
        }

        // Avisos antigos ("Novo lead do site") passam a abrir o lead novo, na Central.
        $newUrl = SalesLeadResource::getUrl('edit', ['record' => $new], panel: 'central');
        DB::table('notifications')
            ->where('data', 'like', '%/crm-leads/'.$lead->id.'/edit%')
            ->orWhere('data', 'like', '%\\/crm-leads\\/'.$lead->id.'\\/edit%')
            ->get()
            ->each(function ($n) use ($lead, $newUrl) {
                $data = json_decode($n->data, true);
                array_walk_recursive($data, function (&$v) use ($lead, $newUrl) {
                    if (is_string($v) && str_contains($v, '/crm-leads/'.$lead->id.'/edit')) {
                        $v = $newUrl;
                    }
                });
                $data['viewData'] = array_merge((array) ($data['viewData'] ?? []), ['scope' => 'central']);
                DB::table('notifications')->where('id', $n->id)->update(['data' => json_encode($data)]);
            });

        CrmLeadInteraction::withoutGlobalScopes()->where('crm_lead_id', $lead->id)->delete();
        $lead->delete();
    }
}

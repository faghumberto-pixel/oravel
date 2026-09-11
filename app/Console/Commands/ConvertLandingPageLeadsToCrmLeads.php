<?php

namespace App\Console\Commands;

use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\LandingPageLead;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ConvertLandingPageLeadsToCrmLeads extends Command
{
    protected $signature = 'leads:convert {--tenant-id= : Converter apenas pra um tenant}';
    protected $description = 'Converter leads da landing page pra CrmLead no painel comercial';

    public function handle()
    {
        $unconvertedLeads = LandingPageLead::where('converted_at', null)->get();

        if ($unconvertedLeads->isEmpty()) {
            $this->info('✅ Nenhum lead pra converter!');
            return;
        }

        $tenantId = $this->option('tenant-id') ?? Tenant::first()->id;
        $count = 0;

        foreach ($unconvertedLeads as $lead) {
            $crmLead = CrmLead::create([
                'tenant_id' => $tenantId,
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'company_name' => $lead->company,
                'segment' => $lead->segment,
                'source' => 'landing_page_' . $lead->product,
                'stage' => 'prospecção',
            ]);

            $userId = auth()->id();
            if (!$userId) {
                $user = \App\Models\User::where('email', 'humberto@oravel.com.br')->first();
                $userId = $user?->id;
            }

            if ($userId) {
                CrmLeadInteraction::create([
                    'tenant_id' => $tenantId,
                    'crm_lead_id' => $crmLead->id,
                    'user_id' => $userId,
                    'channel' => $lead->product === 'wms' ? 'Landing WMS' : 'Landing CRM',
                    'contact_date' => $lead->created_at,
                    'summary' => "Lead capturado da landing page. Segmento: {$lead->segment}",
                    'stage_at_time' => 'prospecção',
                ]);
            }

            $lead->update(['converted_at' => now()]);
            $count++;
        }

        $this->info("✅ $count leads convertidos com sucesso!");
    }
}

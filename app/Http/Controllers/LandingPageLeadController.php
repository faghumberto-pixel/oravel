<?php

namespace App\Http\Controllers;

use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LandingPageLeadController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'company' => 'required|string|max:255',
            'segment' => 'required|string|max:100',
            'product' => 'required|in:wms,crm',
        ]);

        $tenant = Tenant::where('slug', 'oravel')->first() ?? Tenant::first();

        $crmLead = CrmLead::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'company_name' => $validated['company'],
            'segment' => $validated['segment'],
            'source' => 'landing_page_' . $validated['product'],
            'stage' => 'prospecção',
        ]);

        CrmLeadInteraction::create([
            'tenant_id' => $tenant->id,
            'crm_lead_id' => $crmLead->id,
            'user_id' => $tenant->users->first()?->id,
            'channel' => $validated['product'] === 'wms' ? 'Landing WMS' : 'Landing CRM',
            'contact_date' => now(),
            'summary' => "Lead capturado da landing page. Segmento: {$validated['segment']}",
            'stage_at_time' => 'prospecção',
        ]);

        try {
            Mail::to('contato@oravel.com.br')->send(new \App\Mail\NewLeadNotification($crmLead));
        } catch (\Exception $e) {
            \Log::warning('Email não enviado: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead registrado com sucesso!',
            'data' => $crmLead
        ], 201);
    }
}

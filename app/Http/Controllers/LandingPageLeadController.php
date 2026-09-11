<?php

namespace App\Http\Controllers;

use App\Models\LandingPageLead;
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

        $lead = LandingPageLead::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'company' => $validated['company'],
            'segment' => $validated['segment'],
            'product' => $validated['product'],
            'status' => 'novo',
        ]);

        // TODO: Configurar fila de emails
        // Mail::to('contato@oravel.com.br')->queue(new \App\Mail\NewLeadNotification($lead));
        // Mail::to($lead->email)->queue(new \App\Mail\LeadWelcome($lead));

        return response()->json([
            'success' => true,
            'message' => 'Lead registrado com sucesso!',
            'data' => $lead
        ], 201);
    }
}

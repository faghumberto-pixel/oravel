<?php

namespace App\Http\Controllers;

use App\Models\LandingPageLead;
use Illuminate\Http\Request;

class LandingPageLeadsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $html = '<html><head><title>Leads</title><style>body{font-family:Arial;margin:20px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f5f5f5}</style></head><body>';
        $html .= '<h1>Leads das Landing Pages</h1>';

        try {
            $leads = LandingPageLead::orderBy('created_at', 'desc')->limit(100)->get();
            $html .= '<p>Total: ' . $leads->count() . ' leads</p>';

            if ($leads->count() > 0) {
                $html .= '<table><tr><th>Nome</th><th>Email</th><th>Produto</th><th>Status</th><th>Data</th></tr>';
                foreach ($leads as $lead) {
                    $html .= '<tr><td>' . ($lead->name ?? '') . '</td><td>' . ($lead->email ?? '') . '</td><td>' . strtoupper($lead->product ?? '') . '</td><td>' . ($lead->status ?? '') . '</td><td>' . ($lead->created_at ? $lead->created_at->format('d/m/Y H:i') : '') . '</td></tr>';
                }
                $html .= '</table>';
            } else {
                $html .= '<p>Nenhum lead encontrado</p>';
            }
        } catch (\Exception $e) {
            $html .= '<p style="color:red">Erro ao carregar leads: ' . htmlspecialchars($e->getMessage()) . '</p>';
            \Log::error('LandingPageLeads: ' . $e->getMessage());
        }

        $html .= '</body></html>';
        return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function show(LandingPageLead $lead)
    {
        return view('landing-page-leads.show', compact('lead'));
    }

    public function updateStatus(LandingPageLead $lead, Request $request)
    {
        $lead->update([
            'status' => $request->input('status'),
            'contacted_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Lead atualizado com sucesso!');
    }
}

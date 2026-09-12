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
        try {
            $query = LandingPageLead::query();

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                      ->orWhere('email', 'like', "%$search%")
                      ->orWhere('phone', 'like', "%$search%")
                      ->orWhere('company', 'like', "%$search%");
                });
            }

            if ($request->filled('product')) {
                $query->where('product', $request->input('product'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            $leads = $query->orderBy('created_at', 'desc')->paginate(50);

            return view('landing-page-leads.index', compact('leads'));
        } catch (\Exception $e) {
            \Log::error('LandingPageLeads error: ' . $e->getMessage());
            return response()->view('errors.500', [], 500);
        }
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

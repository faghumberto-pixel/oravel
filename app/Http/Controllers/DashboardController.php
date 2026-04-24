<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Aqui entra o segredo: definimos o tenant_id na sessão 
        // para que o TenantScope funcione (exemplo manual)
        if (auth()->check()) {
            session(['tenant_id' => auth()->user()->tenant_id]);
        }

        // O TenantScope filtra automaticamente!
        $assets = Asset::all(); 

        return view('dashboard', compact('assets'));
    }
}
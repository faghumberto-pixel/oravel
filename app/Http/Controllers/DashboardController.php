<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Asset usa Concerns\BelongsToTenant: o global scope filtra por
        // tenant_id do usuário autenticado automaticamente (via Auth, não
        // sessão). App\Models\Scopes\TenantScope não é usado por este
        // model -- não é preciso setar nada manualmente aqui.
        $assets = Asset::all();

        return view('dashboard', compact('assets'));
    }
}

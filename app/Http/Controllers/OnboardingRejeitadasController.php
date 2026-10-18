<?php

namespace App\Http\Controllers;

use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Baixa o CSV das linhas rejeitadas de uma importação de clientes. Link assinado e restrito à empresa que importou. */
class OnboardingRejeitadasController extends Controller
{
    public function __invoke(Request $request, string $arquivo): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $tenantId = Tenancy::current()?->id;

        abort_unless($tenantId && preg_match('/^[0-9a-f-]{36}-[0-9a-f-]{36}\.csv$/', $arquivo) && str_starts_with($arquivo, $tenantId.'-'), 404);
        abort_unless(Storage::disk('local')->exists("onboarding-rejeitadas/{$arquivo}"), 404);

        return Storage::disk('local')->download("onboarding-rejeitadas/{$arquivo}", 'linhas-rejeitadas.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

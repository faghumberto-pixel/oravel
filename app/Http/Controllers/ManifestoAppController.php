<?php

namespace App\Http\Controllers;

use App\Support\AppProfile;
use App\Support\Pacotes;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;

/**
 * Manifesto PWA por perfil x solução do contrato do cliente logado: o app instalado
 * leva o nome e a cor da solução ("Oravel Manutenção"). Parte do manifest-{perfil}.json
 * estático (mesmo id/escopo/ícones, então instalações antigas só se atualizam) e só
 * troca nome e cor. Contrato sem pacote mantém o nome "Oravel".
 */
class ManifestoAppController extends Controller
{
    private const SUFIXOS = [
        AppProfile::TECNICO => ['nome' => ' Técnico', 'curto' => ' Téc.'],
        AppProfile::COLABORADOR => ['nome' => ' Colaborador', 'curto' => ' Colab.'],
    ];

    public function __invoke(string $perfil): JsonResponse
    {
        $base = json_decode((string) file_get_contents(public_path("manifest-{$perfil}.json")), true);

        $user = auth()->user();
        $tenant = Tenancy::current() ?? $user?->tenant;
        $codigo = Pacotes::doTenant($tenant);
        // Com mais de um produto contratado o app leva o nome genérico "Oravel" (não é só "Manutenção" ou só "Vendas").
        $app = $codigo && count(Pacotes::produtosDoTenant($tenant)) === 1 ? (Pacotes::todos()[$codigo]['app'] ?? null) : null;

        if ($app) {
            $sufixo = self::SUFIXOS[$perfil] ?? ['nome' => '', 'curto' => ''];
            $base['name'] = $app['nome'].$sufixo['nome'];
            $base['short_name'] = $app['curto'].$sufixo['curto'];
            $base['theme_color'] = $app['cor'];
        }

        return response()->json($base, 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'private, no-cache',
        ]);
    }
}

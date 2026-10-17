<?php

namespace App\Http\Controllers;

use App\Models\CrmLead;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Baixa o pacote .zip da extensão do WhatsApp (gerado por extensao-whatsapp/build.sh em storage/app/extensao).
 * Só quem pode usar o CRM -- é o mesmo critério da página Integração / API, onde o token é emitido.
 */
class CrmExtensaoDownloadController extends Controller
{
    public static function arquivo(): ?string
    {
        $zips = glob(storage_path('app/extensao/oravel-crm-whatsapp-*.zip')) ?: [];
        natsort($zips);

        return $zips ? end($zips) : null;
    }

    public function __invoke(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->can('viewAny', CrmLead::class), 403);

        $arquivo = self::arquivo();
        abort_unless($arquivo, 404, 'Pacote da extensão ainda não foi gerado.');

        return response()->download($arquivo);
    }
}

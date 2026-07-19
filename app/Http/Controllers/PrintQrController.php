<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PrintQrController extends Controller
{
    public function show(Asset $asset)
    {
        // Aponta pra pagina publica de status do patio (sem login), nao pro
        // edit do painel -- quem escaneia no patio (motorista, portaria,
        // cliente) normalmente nao tem acesso ao admin.
        $url = route('patio.ativo-status', ['asset' => $asset->id]);

        // SVG, nao PNG -- o backend PNG do simple-qrcode exige a extensao
        // Imagick do PHP, que nao esta instalada aqui (nem garantido em
        // todo ambiente); SVG e' o mesmo formato ja usado no QR do Dossie
        // Operacional (routes/web.php) e nao depende de Imagick.
        $qrCodeSvg = QrCode::format('svg')
            ->size(250)
            ->generate($url);

        return response($qrCodeSvg)
            ->header('Content-Type', 'image/svg+xml');
    }
}

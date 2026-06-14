<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PrintQrController extends Controller
{
    public function show(Asset $asset)
    {
        $url = route('filament.admin.resources.assets.edit', [
            'tenant' => $asset->tenant_id,
            'record' => $asset->id
        ]);

        // Geramos o QR Code como um binário PNG puro
        $qrCodeImage = QrCode::format('png')
            ->size(250)
            ->generate($url);

        // Retornamos a imagem diretamente com o Header correto
        return response($qrCodeImage)
            ->header('Content-Type', 'image/png');
    }
}
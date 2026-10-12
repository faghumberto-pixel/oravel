<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\Response;

/** O app do colaborador só abre no celular; no computador mostra um QR para abrir no telefone. */
class ColaboradorSomenteCelular
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('colab', true);

        if (! self::ehCelular($request)) {
            return response()->view('colaborador.desktop', [
                'qr' => QrCode::format('svg')->size(220)->margin(1)->generate(route('colab.home')),
                'url' => route('colab.home'),
            ]);
        }

        return $next($request);
    }

    public static function ehCelular(Request $request): bool
    {
        if ($request->header('Sec-CH-UA-Mobile') === '?1') {
            return true;
        }

        return (bool) preg_match('/iPhone|iPod|Android.+Mobile|Mobile.+Android|Windows Phone|BlackBerry|Opera Mini|IEMobile|webOS/i', (string) $request->userAgent());
    }
}

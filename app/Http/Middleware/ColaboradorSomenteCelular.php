<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\Response;

/** O app do colaborador só abre no celular; no computador mostra um QR para abrir no telefone. */
class ColaboradorSomenteCelular
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('colab', true);

        // O TLS termina no Cloudflare: o Laravel enxerga http e gerava redirecionamentos/links http://. Em página http o
        // navegador esconde a câmera (leitor de QR) e o push. O Cloudflare informa o esquema real do visitante.
        $visitante = (string) $request->header('CF-Visitor');
        $emHttps = str_contains($visitante, '"scheme":"https"') || $request->header('X-Forwarded-Proto') === 'https';

        if (! $emHttps && str_contains($visitante, '"scheme":"http"') && $request->isMethodSafe()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        if ($emHttps) {
            URL::forceScheme('https');
        }

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

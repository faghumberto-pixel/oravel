<?php

namespace App\Support\Assistente;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sessao propria de cada app do celular (Oravel Mobile / Oravel Suprimentos).
 *
 * Os apps instalados sao do MESMO site e, no mesmo aparelho, dividiriam o cookie de sessao e o "lembrar
 * de mim": entrar num desconectaria o outro, e so' caberia um usuario por vez. Aqui cada app ganha:
 *  - cookie de sessao proprio (assistente_<area>_session);
 *  - guard proprio (config/auth.php: assistente_<area>), que tambem separa o cookie "remember_*".
 * Roda no boot do app, antes de a sessao ser lida, e so' para as rotas do assistente (e os POST do
 * Livewire que partem delas); o painel /admin e a Central seguem na sessao normal.
 *
 * Qual app e': pelo caminho (/assistente/suprimentos...), pelo parametro "area" nas telas de entrada/saida,
 * e, nas chamadas do Livewire (/livewire/*), pelo Referer (a pagina de onde partiu).
 */
class Sessao
{
    public const AREAS = ['prospects', 'suprimentos'];

    public static function area(Request $request): ?string
    {
        $caminho = '/'.ltrim($request->path(), '/');

        if (str_starts_with($caminho, '/assistente/r/')) {
            return null; // relatorio publico: sem login
        }

        if ($caminho === '/assistente' || str_starts_with($caminho, '/assistente/')) {
            return self::deCaminho($caminho, (string) $request->input('area'));
        }

        if (str_starts_with($caminho, '/livewire')) {
            $origem = (string) parse_url((string) $request->headers->get('referer'), PHP_URL_PATH);

            if ($origem === '/assistente' || str_starts_with($origem, '/assistente/')) {
                return self::deCaminho($origem, '');
            }
        }

        return null;
    }

    private static function deCaminho(string $caminho, string $areaInformada): string
    {
        if ($caminho === '/assistente/suprimentos' || str_starts_with($caminho, '/assistente/suprimentos/')) {
            return 'suprimentos';
        }

        return $areaInformada === 'suprimentos' ? 'suprimentos' : 'prospects';
    }

    public static function aplicar(Request $request): void
    {
        $area = self::area($request);

        if ($area === null) {
            return;
        }

        config(['session.cookie' => "assistente_{$area}_session"]);
        Auth::shouldUse("assistente_{$area}");
    }
}

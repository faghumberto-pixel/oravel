<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitiza o HTML das aulas da Academia. Diferente do sanitizador padrao do Filament, aceita o
 * atributo `class` (os mockups usam as classes mk-* do layout da Academia) e imagens/figuras
 * LOCAIS (/academy/...). Nunca aceita script, iframe, estilo inline, links com javascript: nem
 * imagens de outro dominio.
 */
class LessonHtml
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): string
    {
        return (string) (self::sanitizer()->sanitize((string) $html));
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowElement('figure', ['class'])
                ->allowElement('figcaption', ['class'])
                ->allowElement('img', ['src', 'alt', 'class', 'loading', 'width', 'height'])
                ->allowAttribute('class', '*')
                ->allowRelativeMedias(true)
                ->allowMediaHosts([])
                ->forceAttribute('img', 'loading', 'lazy')
                ->allowLinkSchemes(['https', 'mailto'])
                ->allowRelativeLinks(false)
        );
    }
}

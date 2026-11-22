<?php

namespace App\Support\Assistente;

/**
 * Identidade visual de cada chat do app Oravel Mobile (uma cor e um icone por area),
 * pra o usuario distinguir os apps instalados na tela do celular. Unica fonte:
 * layout, manifestos, telas de entrada e o interior do chat leem daqui.
 * Para uma area nova: adicionar uma entrada, gerar icon-{area}-192/512.png e o manifest.
 */
class Areas
{
    /** @var array<string, array<string, string>> */
    private const TEMAS = [
        'prospects' => [
            'nome' => 'Oravel Mobile',
            'subtitulo' => 'Prospecção',
            'manifesto' => 'manifest-assistente.json',
            'icone' => 'icon-prospects-192.png',
            'cor' => '#ea580c',
            'escura' => '#c2410c',
            'fundo' => '#fff7ed',
        ],
        'suprimentos' => [
            'nome' => 'Oravel Suprimentos',
            'subtitulo' => 'Estoque e compras',
            'manifesto' => 'manifest-assistente-suprimentos.json',
            'icone' => 'icon-suprimentos-192.png',
            'cor' => '#2563eb',
            'escura' => '#1d4ed8',
            'fundo' => '#eff6ff',
        ],
        'intelligence' => [
            'nome' => 'Oravel Intelligence',
            'subtitulo' => 'Saúde e evolução',
            'manifesto' => 'manifest-assistente-intelligence.json',
            'icone' => 'icon-intelligence-192.png',
            'cor' => '#4f46e5',
            'escura' => '#4338ca',
            'fundo' => '#eef2ff',
            'login' => 'Entre para consultar a saúde e a evolução da sua empresa.',
        ],
    ];

    /** @return array<string, string> */
    public static function tema(?string $area): array
    {
        return self::TEMAS[$area] ?? self::TEMAS['prospects'];
    }
}

<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class ViteHelper
{
    public static function renderAssets(array $files): HtmlString
    {
        if (!app()->isProduction()) {
            return new HtmlString(implode('', array_map(fn($f) => self::renderDev($f), $files)));
        }

        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $html = '';

        foreach ($files as $file) {
            if (isset($manifest[$file])) {
                $asset = $manifest[$file];
                if (str_ends_with($file, '.css')) {
                    $html .= "<link rel=\"stylesheet\" href=\"" . asset('build/' . $asset['file']) . "\">\n";
                } else if (str_ends_with($file, '.js')) {
                    $html .= "<script type=\"module\" src=\"" . asset('build/' . $asset['file']) . "\"></script>\n";
                }
            }
        }

        return new HtmlString($html);
    }

    private static function renderDev(string $file): string
    {
        if (str_ends_with($file, '.css')) {
            return "<link rel=\"stylesheet\" href=\"http://127.0.0.1:5173/{$file}\">\n";
        }
        return "<script type=\"module\" src=\"http://127.0.0.1:5173/@vite/client\"></script><script type=\"module\" src=\"http://127.0.0.1:5173/{$file}\"></script>\n";
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Todo Resource do painel precisa de rotulo proprio (modelLabel/pluralModelLabel): sem ele o Filament
 * deriva do nome da classe em ingles ("internal units", "parts requests"...) e isso aparece no titulo
 * e no breadcrumb da pagina. Achado em 2026-10-23.
 */
class RotulosDosResourcesTest extends TestCase
{
    public function test_nenhum_resource_do_painel_usa_o_rotulo_padrao_em_ingles(): void
    {
        $falhas = [];

        foreach (glob(app_path('Filament/Resources/*Resource.php')) as $arquivo) {
            $classe = 'App\\Filament\\Resources\\'.basename($arquivo, '.php');
            if (! class_exists($classe) || (new \ReflectionClass($classe))->isAbstract()) {
                continue;
            }

            $padrao = (string) Str::of($classe::getModel())->classBasename()->kebab()->replace('-', ' ')->plural();
            if ($classe::getPluralModelLabel() === $padrao) {
                $falhas[] = class_basename($classe)." -> \"{$padrao}\"";
            }
        }

        $this->assertSame([], $falhas, 'defina $modelLabel e $pluralModelLabel em portugues');
    }
}

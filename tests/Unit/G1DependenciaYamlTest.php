<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * G1: o instalador do catalogo le YAML em runtime. O deploy usa `composer install --no-dev`, entao symfony/yaml precisa estar
 * em "require" (nao em "require-dev"), senao catalogo:instalar, o provisionamento e as atualizacoes de template quebram em PROD.
 */
class G1DependenciaYamlTest extends TestCase
{
    public function test_symfony_yaml_e_dependencia_de_producao(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);
        $lock = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.lock'), true);

        $this->assertArrayHasKey('symfony/yaml', $composer['require']);
        $this->assertContains('symfony/yaml', array_column($lock['packages'], 'name'), 'precisa estar em packages (producao), nao em packages-dev');
    }
}

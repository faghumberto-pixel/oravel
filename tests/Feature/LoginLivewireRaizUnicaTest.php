<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

/**
 * Regressão (25/11/2026): a view de login tinha <link> soltos antes do <div> principal. O Livewire exige um único elemento raiz:
 * o componente passou a ter como raiz o primeiro <link>, o formulário ficou FORA do componente, o Livewire não o interceptou e o
 * login enviava um POST nativo a /admin/login (erro 405) para qualquer usuário.
 */
class LoginLivewireRaizUnicaTest extends TestCase
{
    public function test_o_formulario_de_login_fica_dentro_do_componente_livewire(): void
    {
        $html = $this->get('/admin/login')->assertOk()->getContent();

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        $x = new DOMXPath($dom);

        $form = $x->query('//form[@*[name()="wire:submit"]="authenticate"]');
        $this->assertSame(1, $form->length, 'a tela de login não tem o formulário esperado');

        // o formulário precisa ter, como ancestral, o elemento raiz do componente de login
        $raizes = $x->query('ancestor::*[@*[name()="wire:id"]]', $form->item(0));
        $this->assertGreaterThan(0, $raizes->length, 'o formulário de login está FORA do componente Livewire (a view tem mais de uma raiz)');

        // e a raiz do componente tem de ser um elemento de conteúdo, nunca uma tag de <head> solta
        $this->assertNotContains($raizes->item(0)->nodeName, ['link', 'meta', 'style', 'script'], 'a raiz do componente é uma tag solta');
    }
}

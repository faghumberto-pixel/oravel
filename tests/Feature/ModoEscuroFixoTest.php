<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Tests\TestCase;

/** Somente modo escuro nos paineis admin e Central (pedido do usuario 2026-11-03): sem seletor claro/escuro. */
class ModoEscuroFixoTest extends TestCase
{
    public function test_paineis_admin_e_central_sao_somente_escuros(): void
    {
        foreach (['admin', 'central'] as $id) {
            $painel = Filament::getPanel($id);
            $this->assertTrue($painel->hasDarkMode(), $id);
            $this->assertTrue($painel->hasDarkModeForced(), "$id deve forcar o escuro (sem seletor)");
        }
    }

    public function test_pagina_do_admin_nao_forca_tema_claro_no_navegador(): void
    {
        $this->assertStringNotContainsString("setItem('theme','light')", file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php')));
    }
}

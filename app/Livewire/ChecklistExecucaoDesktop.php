<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;

/**
 * Mesma execucao do checklist (mesma logica, validacao e regras da versao de celular), numa tela larga para o desktop: tabela por secao,
 * sem a moldura de celular. Quem decide qual abrir e o link: o painel abre esta, o app do colaborador e o QR abrem a de celular.
 */
#[Layout('layouts.checklist-desktop')]
class ChecklistExecucaoDesktop extends ChecklistExecucaoMobile
{
    public function render()
    {
        return view('livewire.checklist-execucao-desktop');
    }
}

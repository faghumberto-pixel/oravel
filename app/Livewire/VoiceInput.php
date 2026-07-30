<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * Componente de entrada de voz para mobile -- grava áudio, transcreve via Web Speech API,
 * permite conferência antes de usar o texto. Emite evento para o componente pai atualizar
 * a propriedade Livewire correta (via #[On] listener).
 *
 * Uso: @livewire('voice-input', ['modelName' => 'propertyNameNoComponentPai'])
 */
class VoiceInput extends Component
{
    public string $modelName;

    public function render()
    {
        return view('livewire.voice-input');
    }
}

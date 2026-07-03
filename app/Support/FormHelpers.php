<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class FormHelpers
{
    /**
     * Botão de ditado por voz (Web Speech API) para campos de texto.
     * Anexa o texto reconhecido ao final do textarea mais próximo.
     */
    public static function voiceButton(): HtmlString
    {
        return new HtmlString('
            <button type="button" onclick="oravelStartDictation(this)" class="flex items-center gap-1 text-xs font-bold text-primary-600 hover:text-primary-800">
                🎤 GRAVAR VOZ
            </button>
            <script>
                if (typeof oravelStartDictation === "undefined") {
                    window.oravelStartDictation = function(btn) {
                        if (!window.webkitSpeechRecognition) {
                            alert("Navegador não suporta reconhecimento de voz.");
                            return;
                        }
                        const recognition = new webkitSpeechRecognition();
                        recognition.lang = "pt-BR";
                        recognition.continuous = false;
                        const originalLabel = btn.innerText;
                        btn.innerText = "🔴 Ouvindo...";
                        recognition.onresult = (e) => {
                            const container = btn.closest(".fi-fo-field-wrp") || btn.parentElement.parentElement;
                            const textarea = container ? container.querySelector("textarea") : null;
                            if (textarea) {
                                textarea.value += (textarea.value ? " " : "") + e.results[0][0].transcript;
                                textarea.dispatchEvent(new Event("input"));
                            }
                            btn.innerText = originalLabel;
                        };
                        recognition.onerror = () => { btn.innerText = originalLabel; };
                        recognition.onend = () => { btn.innerText = originalLabel; };
                        recognition.start();
                    };
                }
            </script>
        ');
    }
}

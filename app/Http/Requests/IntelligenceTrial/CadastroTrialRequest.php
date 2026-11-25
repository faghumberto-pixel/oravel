<?php

namespace App\Http\Requests\IntelligenceTrial;

use App\Domain\Intelligence\Trial\Support\TelefoneBr;
use Illuminate\Foundation\Http\FormRequest;

class CadastroTrialRequest extends FormRequest
{
    /** Erro de validação nunca volta para a página do site (que não mostra erros): vai para a tela de cadastro do app, com os dados preenchidos. */
    protected $redirectRoute = 'intelligence.trial.create';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'min:2', 'max:120'],
            'empresa' => ['required', 'string', 'min:2', 'max:120'],
            'whatsapp' => ['required', 'string', function ($attr, $value, $fail) {
                if (TelefoneBr::e164($value) === null) {
                    $fail('Informe um WhatsApp com DDD, por exemplo (19) 99999-9999.');
                }
            }],
            'email' => ['required', 'string', 'email:rfc', 'max:160', function ($attr, $value, $fail) {
                $dominio = strtolower(substr(strrchr((string) $value, '@') ?: '', 1));
                if (in_array($dominio, (array) config('intelligence_trial.email_bloqueados', []), true)) {
                    $fail('Use um e-mail da sua empresa ou pessoal fixo (e-mails temporários não são aceitos).');
                }
            }],
            'aceite' => ['accepted'],
            // Honeypot: humano nunca preenche.
            'site' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'Informe seu nome.',
            'empresa.required' => 'Informe o nome da empresa.',
            'whatsapp.required' => 'Informe seu WhatsApp.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'aceite.accepted' => 'Para continuar, aceite os Termos de Uso e a Política de Privacidade.',
            'site.prohibited' => 'Não foi possível enviar.',
        ];
    }

    public function whatsappE164(): string
    {
        return TelefoneBr::e164($this->input('whatsapp'));
    }
}

<?php

namespace App\Http\Requests\IntelligenceTrial;

use App\Domain\Intelligence\Trial\Support\TelefoneBr;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CadastroTrialRequest extends FormRequest
{
    /** Erro de validação nunca volta para a página do site (que não mostra erros): vai para a tela de cadastro do app, com os dados preenchidos. */
    protected $redirectRoute = 'intelligence.trial.create';

    /** A senha nunca volta para o formulário (old input) nem para a sessão. */
    protected $dontFlash = ['senha', 'password', 'password_confirmation'];

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
            'senha' => ['required', 'string', 'max:72', Password::min(8)->letters()->numbers()],
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
            'senha.required' => 'Crie uma senha para entrar depois.',
            'senha.min' => 'A senha precisa ter pelo menos 8 caracteres.',
            'senha.letters' => 'A senha precisa ter pelo menos uma letra.',
            'senha.numbers' => 'A senha precisa ter pelo menos um número.',
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

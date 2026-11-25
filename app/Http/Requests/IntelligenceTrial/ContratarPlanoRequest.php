<?php

namespace App\Http\Requests\IntelligenceTrial;

use App\Rules\CpfCnpj;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContratarPlanoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'plano' => ['nullable', 'string', 'max:20'],
            'razao_social' => ['required', 'string', 'min:3', 'max:160'],
            'cnpj' => ['required', 'string', new CpfCnpj],
            'email' => ['required', 'email', 'max:160'],
            'responsavel' => ['required', 'string', 'min:3', 'max:120'],
            'forma_pagamento' => ['required', Rule::in(['PIX', 'BOLETO', 'CREDIT_CARD', 'UNDEFINED'])],
            'aceite_contrato' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'razao_social.required' => 'Informe a razão social.',
            'cnpj.required' => 'Informe o CNPJ.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'responsavel.required' => 'Informe o nome do responsável.',
            'forma_pagamento.required' => 'Escolha a forma de pagamento.',
            'aceite_contrato.accepted' => 'Leia e aceite o contrato para continuar.',
        ];
    }
}

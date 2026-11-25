<?php

namespace App\Http\Requests\IntelligenceTrial;

use App\Domain\Intelligence\Trial\Support\TelefoneBr;
use Illuminate\Foundation\Http\FormRequest;

class NovoLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'whatsapp' => ['required', 'string', function ($attr, $value, $fail) {
                if (TelefoneBr::e164($value) === null) {
                    $fail('Informe um WhatsApp com DDD, por exemplo (19) 99999-9999.');
                }
            }],
            'site' => ['prohibited'],
        ];
    }

    public function whatsappE164(): string
    {
        return TelefoneBr::e164($this->input('whatsapp'));
    }
}

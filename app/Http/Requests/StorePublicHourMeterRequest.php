<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Único apontamento por vez, vindo do link público sem login (funcionário
 * do cliente locatário) -- diferente de StoreHourMeterRequest (técnico
 * logado, lote offline). recorded_by_name é obrigatório aqui: não há User
 * pra identificar quem registrou, então o nome digitado é a única
 * identificação que sobra pro relatório de auditoria.
 */
class StorePublicHourMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recorded_by_name' => ['required', 'string', 'min:3', 'max:255'],
            'reading' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'recorded_at' => ['required', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reset_confirmed' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'file', 'mimes:jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'recorded_by_name' => 'seu nome',
            'reading' => 'horímetro',
            'recorded_at' => 'data/hora do apontamento',
            'photo' => 'foto de validação',
        ];
    }
}

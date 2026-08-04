<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreHourMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Payload em lote: 'readings' é um array de apontamentos capturados
     * offline no device. A foto de cada item vem via multipart, indexada
     * pela mesma posição do array (readings[0][photo], readings[1][photo]...).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'readings' => ['required', 'array', 'min:1', 'max:200'],

            'readings.*.client_uuid' => ['required', 'uuid'],
            'readings.*.asset_id' => ['required', 'uuid', 'exists:assets,id'],
            'readings.*.reading' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'readings.*.recorded_at' => ['required', 'date'],
            'readings.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'readings.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'readings.*.reset_confirmed' => ['nullable', 'boolean'],
            'readings.*.photo' => ['nullable', 'file', 'mimes:jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'readings' => 'lote de apontamentos',
            'readings.*.client_uuid' => 'identificador local',
            'readings.*.asset_id' => 'equipamento',
            'readings.*.reading' => 'horímetro',
            'readings.*.recorded_at' => 'data/hora do apontamento',
            'readings.*.photo' => 'foto de validação',
        ];
    }
}

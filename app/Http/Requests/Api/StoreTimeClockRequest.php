<?php

namespace App\Http\Requests\Api;

use App\Models\TimeClock;
use Illuminate\Foundation\Http\FormRequest;

class StoreTimeClockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Payload em lote: 'batidas' é um array de registros de ponto
     * capturados offline no device (mesmo padrão de StoreHourMeterRequest).
     * recorded_at NÃO é aceito aqui de propósito -- é sempre now() do
     * servidor no momento do sync, nunca a hora informada pelo cliente
     * (evita fraude de relógio do dispositivo). device_recorded_at é a
     * hora local, guardada só como evidência.
     */
    public function rules(): array
    {
        return [
            'batidas' => ['required', 'array', 'min:1', 'max:50'],

            'batidas.*.client_uuid' => ['required', 'uuid'],
            'batidas.*.employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'batidas.*.tipo' => ['required', 'in:'.implode(',', array_keys(TimeClock::tipoLabels()))],
            'batidas.*.device_recorded_at' => ['required', 'date'],
            'batidas.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'batidas.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function attributes(): array
    {
        return [
            'batidas' => 'lote de batidas de ponto',
            'batidas.*.client_uuid' => 'identificador local',
            'batidas.*.employee_id' => 'colaborador',
            'batidas.*.tipo' => 'tipo de batida',
            'batidas.*.device_recorded_at' => 'data/hora do dispositivo',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\FreightRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FreightRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tipo' => FreightRecord::TIPO_PROPRIO,
            'valor' => $this->faker->randomFloat(2, 200, 2500),
            'origem' => 'Base '.$this->faker->city(),
            'destino' => 'Obra '.$this->faker->city(),
            'km_percorrido' => $this->faker->randomFloat(2, 15, 400),
            'data' => $this->faker->dateTimeBetween('-60 days', 'now'),
            'horas_motorista' => $this->faker->randomFloat(2, 1, 10),
            'custo_motorista' => $this->faker->randomFloat(2, 40, 350),
        ];
    }

    public function terceirizado(): static
    {
        return $this->state(fn () => [
            'tipo' => FreightRecord::TIPO_TERCEIRIZADO,
            'horas_motorista' => null,
            'custo_motorista' => null,
        ]);
    }
}

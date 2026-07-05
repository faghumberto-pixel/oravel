<?php

namespace Database\Factories;

use App\Models\FleetMaintenancePlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FleetMaintenancePlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tipo_servico' => FleetMaintenancePlan::TIPO_REVISAO,
            'intervalo_km' => 10000,
            'intervalo_dias' => 90,
            'ultima_execucao_km' => $this->faker->numberBetween(0, 8000),
            'ultima_execucao_data' => $this->faker->dateTimeBetween('-80 days', '-10 days'),
        ];
    }
}

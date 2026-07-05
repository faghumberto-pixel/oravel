<?php

namespace Database\Factories;

use App\Models\FleetVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FleetVehicleFactory extends Factory
{
    private static array $modelos = [
        'Caminhão Munck VW Constellation', 'Carreta Prancha Randon',
        'Caminhão Toco Mercedes-Benz Atego', 'Guincho Ford Cargo',
    ];

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'placa' => strtoupper($this->faker->bothify('???#?##')),
            'modelo' => $this->faker->randomElement(self::$modelos),
            'tipo' => 'caminhao',
            'capacidade_carga' => $this->faker->randomFloat(2, 2000, 15000),
            'status' => FleetVehicle::STATUS_DISPONIVEL,
            'km_atual' => $this->faker->numberBetween(5000, 180000),
            'tag_sem_parar' => strtoupper(Str::random(10)),
        ];
    }

    public function emManutencao(): static
    {
        return $this->state(fn () => ['status' => FleetVehicle::STATUS_MANUTENCAO]);
    }
}

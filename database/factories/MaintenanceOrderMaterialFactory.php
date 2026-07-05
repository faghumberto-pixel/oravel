<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MaintenanceOrderMaterialFactory extends Factory
{
    private static array $parts = [
        'Filtro de Ar', 'Filtro de Óleo', 'Válvula Hidráulica', 'Correia Dentada',
        'Mangueira Hidráulica', 'Rolamento', 'Vela de Ignição', 'Bateria 12V',
        'Retentor', 'Junta de Vedação', 'Pastilha de Freio', 'Sensor de Temperatura',
    ];

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => $this->faker->randomElement(self::$parts),
            'quantity' => $this->faker->numberBetween(1, 4),
        ];
    }
}

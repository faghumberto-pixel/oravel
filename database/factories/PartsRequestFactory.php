<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PartsRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quantity' => $this->faker->numberBetween(1, 5),
            'status' => 'pendente',
            'cost_at_time' => $this->faker->randomFloat(2, 20, 600),
        ];
    }

    public function pedida(): static
    {
        return $this->state(fn () => ['status' => 'pedida']);
    }

    public function entregue(): static
    {
        return $this->state(fn () => ['status' => 'entregue']);
    }
}

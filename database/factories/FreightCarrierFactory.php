<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FreightCarrierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'nome' => $this->faker->company().' Transportes',
            'documento' => $this->faker->numerify('##.###.###/####-##'),
            'contato_nome' => $this->faker->name(),
            'contato_telefone' => $this->faker->numerify('(##) 9####-####'),
        ];
    }
}

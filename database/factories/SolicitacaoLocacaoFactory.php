<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SolicitacaoLocacaoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'purpose' => $this->faker->randomElement(['Obra civil', 'Evento temporário', 'Substituição de frota', 'Reforço operacional']),
            'data_saida_prevista' => $this->faker->dateTimeBetween('-2 months', '+2 months')->format('Y-m-d'),
            'status_comercial' => 'proposta_em_andamento',
            'observations' => $this->faker->optional()->sentence(),
        ];
    }

    public function fechada(): static
    {
        return $this->state(fn () => ['status_comercial' => 'contrato_fechado']);
    }

    public function cancelada(): static
    {
        return $this->state(fn () => ['status_comercial' => 'cancelado']);
    }
}

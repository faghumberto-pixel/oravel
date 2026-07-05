<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Nota: o model Contract declara mais campos no $fillable (prohibit_sublease,
 * maintenance_clause, initial_horimeter, initial_odometer, cep_obra,
 * latitude_obra, longitude_obra, legal_forum, insurance_details) do que a
 * tabela real possui -- essa factory usa só as colunas que existem de fato.
 */
class ContractFactory extends Factory
{
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-18 months', '-1 month');

        return [
            'id' => (string) Str::uuid(),
            'contract_number' => 'CTR-'.$this->faker->unique()->numerify('####-####'),
            'status' => 'Ativo',
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => (clone $startDate)->modify('+'.$this->faker->numberBetween(6, 24).' months')->format('Y-m-d'),
            'price' => $this->faker->randomFloat(2, 2000, 45000),
            'payment_method' => $this->faker->randomElement(['Boleto', 'PIX', 'Transferência', 'Cartão de Crédito']),
            'observations' => $this->faker->optional()->sentence(),
            'usage_purpose' => $this->faker->randomElement(['Obra civil', 'Evento temporário', 'Operação industrial', 'Manutenção de frota']),
            'required_nrs' => $this->faker->randomElement(['NR-12, NR-18', 'NR-12', 'NR-18, NR-35', null]),
            'is_active' => true,
        ];
    }

    /** Contrato ainda em negociação/rascunho, sem vigência confirmada. */
    public function rascunho(): static
    {
        return $this->state(fn () => [
            'status' => 'Draft',
            'is_active' => false,
            'end_date' => null,
        ]);
    }

    /** Contrato encerrado no passado. */
    public function encerrado(): static
    {
        $start = $this->faker->dateTimeBetween('-3 years', '-13 months');
        $end = (clone $start)->modify('+'.$this->faker->numberBetween(6, 12).' months');

        return $this->state(fn () => [
            'status' => 'Encerrado',
            'is_active' => false,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
        ]);
    }
}

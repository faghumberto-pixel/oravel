<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssetFactory extends Factory
{
    /**
     * Tipos de equipamento coerentes com uma locadora (geradores, plataformas,
     * prensas, empilhadeiras, compressores), com faixa de capacidade plausível.
     */
    private static array $equipmentTypes = [
        ['category' => 'Gerador', 'unit' => 'kVA', 'capacities' => [15, 30, 60, 100, 180, 250, 500]],
        ['category' => 'Plataforma Elevatória', 'unit' => 'm', 'capacities' => [8, 12, 16, 20]],
        ['category' => 'Prensa Hidráulica', 'unit' => 'ton', 'capacities' => [10, 30, 60, 100]],
        ['category' => 'Empilhadeira', 'unit' => 'ton', 'capacities' => [1.5, 2.5, 3.5, 5]],
        ['category' => 'Compressor de Ar', 'unit' => 'pcm', 'capacities' => [100, 185, 375]],
    ];

    public function definition(): array
    {
        $type = $this->faker->randomElement(self::$equipmentTypes);
        $capacity = $this->faker->randomElement($type['capacities']);
        $sequence = str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 3, '0', STR_PAD_LEFT);
        $prefix = strtoupper(Str::substr($type['category'], 0, 3));

        return [
            'id' => (string) Str::uuid(),
            'name' => "{$type['category']} {$capacity}{$type['unit']}",
            'tag' => "{$prefix}-{$sequence}",
            'patrimonio' => 'PAT'.$this->faker->unique()->numerify('######'),
            'serial_number' => strtoupper($this->faker->bothify('??########')),
            'status' => $this->faker->randomElement(['disponivel', 'disponivel', 'locado', 'locado', 'manutencao', 'operando']),
            'criticality' => $this->faker->randomElement(['low', 'medium', 'medium', 'high']),
            'criticality_level' => $this->faker->randomElement(['Baixa', 'Média', 'Média', 'Alta']),
            'asset_category' => $type['category'],
            'capacity_value' => (string) $capacity,
            'capacity_unit' => $type['unit'],
            'acquisition_value' => $this->faker->randomFloat(2, 20000, 350000),
            'residual_value' => 0,
            'useful_life_years' => $this->faker->numberBetween(5, 15),
            'acquisition_date' => $this->faker->dateTimeBetween('-6 years', '-2 months')->format('Y-m-d'),
            'manufacturing_year' => $this->faker->numberBetween(2015, 2025),
            'horimetro_atual' => $this->faker->randomFloat(2, 0, 8000),
            'last_horimetro' => 0,
        ];
    }

    /** Ativo veterano: muito uso acumulado (mais historico de manutencao vem do seeder). */
    public function veterano(): static
    {
        return $this->state(fn () => [
            'acquisition_date' => $this->faker->dateTimeBetween('-8 years', '-4 years')->format('Y-m-d'),
            'horimetro_atual' => $this->faker->randomFloat(2, 6000, 15000),
        ]);
    }

    /** Ativo novo: pouco uso, adquirido recentemente. */
    public function novo(): static
    {
        return $this->state(fn () => [
            'acquisition_date' => $this->faker->dateTimeBetween('-3 months', '-2 weeks')->format('Y-m-d'),
            'horimetro_atual' => $this->faker->randomFloat(2, 0, 150),
        ]);
    }
}

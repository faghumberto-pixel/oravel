<?php

namespace Database\Factories;

use App\Models\EquipmentMovement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EquipmentMovementFactory extends Factory
{
    public function definition(): array
    {
        $type = $this->faker->randomElement([EquipmentMovement::TYPE_MOBILIZACAO, EquipmentMovement::TYPE_DESMOBILIZACAO]);

        return [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'status' => EquipmentMovement::STATUS_AGUARDANDO_VISTORIA,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function emAndamento(): static
    {
        return $this->state(fn () => [
            'status' => EquipmentMovement::STATUS_EM_ANDAMENTO,
            'started_at' => $this->faker->dateTimeBetween('-2 weeks', '-2 days'),
        ]);
    }

    public function concluido(): static
    {
        $startedAt = $this->faker->dateTimeBetween('-2 months', '-1 week');

        return $this->state(fn () => [
            'status' => EquipmentMovement::STATUS_CONCLUIDO,
            'started_at' => $startedAt,
            'completed_at' => (clone $startedAt)->modify('+'.$this->faker->numberBetween(1, 3).' days'),
        ]);
    }
}

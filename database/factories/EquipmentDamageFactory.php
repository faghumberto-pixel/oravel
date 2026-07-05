<?php

namespace Database\Factories;

use App\Models\EquipmentDamage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EquipmentDamageFactory extends Factory
{
    private static array $descriptions = [
        'Amassado na lateral da carenagem durante transporte',
        'Vazamento de óleo identificado na vistoria de retorno',
        'Painel de controle com trinca visível',
        'Pneu/rodante com desgaste acima do esperado',
        'Estrutura com sinais de corrosão avançada',
        'Cabo elétrico rompido próximo ao motor',
    ];

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'severity' => $this->faker->randomElement([
                EquipmentDamage::SEVERITY_LEVE,
                EquipmentDamage::SEVERITY_LEVE,
                EquipmentDamage::SEVERITY_MODERADA,
                EquipmentDamage::SEVERITY_GRAVE,
            ]),
            'description' => $this->faker->randomElement(self::$descriptions),
            'requires_replacement' => false,
            'status' => EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR,
            'estimated_cost' => $this->faker->randomFloat(2, 200, 8000),
        ];
    }

    public function emCobranca(): static
    {
        return $this->state(fn () => [
            'status' => EquipmentDamage::STATUS_EM_COBRANCA,
            'supervisor_reviewed_at' => $this->faker->dateTimeBetween('-1 month', '-1 week'),
            'commercial_reviewed_at' => $this->faker->dateTimeBetween('-3 weeks', '-2 days'),
        ]);
    }

    public function resolvido(): static
    {
        return $this->state(fn () => [
            'status' => EquipmentDamage::STATUS_RESOLVIDO,
            'supervisor_reviewed_at' => $this->faker->dateTimeBetween('-2 months', '-1 month'),
            'commercial_reviewed_at' => $this->faker->dateTimeBetween('-1 month', '-2 weeks'),
        ]);
    }
}

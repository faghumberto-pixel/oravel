<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MaintenanceOrderChecklistFactory extends Factory
{
    private static array $items = [
        ['category' => 'Motor', 'item_name' => 'Verificar nível de óleo'],
        ['category' => 'Motor', 'item_name' => 'Inspecionar correias e mangueiras'],
        ['category' => 'Elétrica', 'item_name' => 'Testar sistema de partida'],
        ['category' => 'Elétrica', 'item_name' => 'Verificar cabeamento e conexões'],
        ['category' => 'Hidráulica', 'item_name' => 'Checar vazamentos no sistema hidráulico'],
        ['category' => 'Estrutural', 'item_name' => 'Inspecionar solda e estrutura'],
        ['category' => 'Segurança', 'item_name' => 'Testar dispositivos de segurança'],
        ['category' => 'Geral', 'item_name' => 'Limpeza e organização do equipamento'],
    ];

    public function definition(): array
    {
        $item = $this->faker->randomElement(self::$items);

        return [
            'id' => (string) Str::uuid(),
            'category' => $item['category'],
            'item_name' => $item['item_name'],
            'instructions' => null,
            'is_completed' => false,
            'is_template' => false,
            'checklist_type' => 'Corretiva',
        ];
    }

    public function completo(): static
    {
        return $this->state(fn () => ['is_completed' => true]);
    }
}

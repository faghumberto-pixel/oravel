<?php
namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MaterialFactory extends Factory {
    public function definition(): array {
        return [
            'id' => (string) Str::uuid(),
            'name' => $this->faker->words(2, true),
            'sku' => strtoupper($this->faker->bothify('???-####')), // Gera algo como ABC-1234
            'tenant_id' => 1,
        ];
    }
}

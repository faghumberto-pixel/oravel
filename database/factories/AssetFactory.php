<?php
namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssetFactory extends Factory {
    public function definition(): array {
        return [
            'id' => (string) Str::uuid(),
            'name' => $this->faker->words(2, true),
            'tenant_id' => 1,
        ];
    }
}

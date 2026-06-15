<?php
namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SupplierFactory extends Factory {
    public function definition(): array {
        return [
            'id' => (string) Str::uuid(),
            'name' => $this->faker->company,
            'email' => $this->faker->unique()->safeEmail,
            'tenant_id' => 1,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $nome = fake()->unique()->company();

        return [
            'name' => $nome,
            'slug' => \Illuminate\Support\Str::slug($nome).'-'.fake()->unique()->numerify('####'),
            'status' => 'active',
            'mrr_value' => 0,
        ];
    }
}

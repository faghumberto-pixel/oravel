<?php

namespace Database\Factories;

use App\Models\AccountReceivable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AccountReceivable> */
class AccountReceivableFactory extends Factory
{
    protected $model = AccountReceivable::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'description' => 'Locação '.fake()->monthName(),
            'amount' => fake()->randomFloat(2, 500, 20000),
            'due_date' => now()->addDays(fake()->numberBetween(1, 30))->toDateString(),
            'status' => 'pendente',
        ];
    }
}

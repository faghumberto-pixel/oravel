<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => $this->faker->company(),
            'document' => $this->faker->numerify('##.###.###/####-##'),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->numerify('(##) ####-####'),
            'bank_account_pix' => $this->faker->boolean(70) ? $this->faker->email() : null,
            'compliance_ceis_cnep' => $this->faker->boolean(85),
            'lista_trabalho_escravo' => $this->faker->boolean(90),
            'termo_lgpd' => $this->faker->boolean(75),
        ];
    }
}

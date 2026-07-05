<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ClientFactory extends Factory
{
    private static array $suffixes = ['Construções Ltda', 'Engenharia S.A.', 'Locações e Serviços', 'Empreendimentos', 'Obras e Montagens'];

    public function definition(): array
    {
        $name = $this->faker->company().' '.$this->faker->randomElement(self::$suffixes);

        return [
            'id' => (string) Str::uuid(),
            'name' => $name,
            'activity_type' => $this->faker->randomElement(['Construção Civil', 'Eventos', 'Mineração', 'Industrial']),
            'cpf_cnpj' => $this->faker->numerify('##.###.###/####-##'),
            'contact_name' => $this->faker->name(),
            'cep' => $this->faker->numerify('#####-###'),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'uf' => $this->faker->randomElement(['SP', 'RJ', 'MG', 'PR', 'SC', 'BA', 'PA']),
            'phone' => $this->faker->numerify('(##) ####-####'),
            'whatsapp' => $this->faker->numerify('(##) 9####-####'),
        ];
    }
}

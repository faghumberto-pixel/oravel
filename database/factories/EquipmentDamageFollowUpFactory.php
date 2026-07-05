<?php

namespace Database\Factories;

use App\Models\EquipmentDamageFollowUp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EquipmentDamageFollowUpFactory extends Factory
{
    public function definition(): array
    {
        $contactDate = $this->faker->dateTimeBetween('-3 weeks', '-1 day');

        return [
            'id' => (string) Str::uuid(),
            'contact_date' => $contactDate,
            'channel' => $this->faker->randomElement([
                EquipmentDamageFollowUp::CHANNEL_TELEFONE,
                EquipmentDamageFollowUp::CHANNEL_EMAIL,
                EquipmentDamageFollowUp::CHANNEL_WHATSAPP,
            ]),
            'summary' => $this->faker->randomElement([
                'Cliente informado sobre o valor estimado do reparo',
                'Aguardando retorno do cliente sobre a cobrança',
                'Cliente contestou o valor, negociação em andamento',
                'Confirmado pagamento parcial, restante a combinar',
            ]),
            'next_action' => $this->faker->optional()->sentence(),
            'next_action_date' => $this->faker->optional()->dateTimeBetween('+1 day', '+3 weeks'),
        ];
    }
}

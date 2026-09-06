<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\DocumentSignature;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentSignatureFactory extends Factory
{
    protected $model = DocumentSignature::class;

    public function definition(): array
    {
        return [
            'signable_type' => Contract::class,
            'signer_name' => $this->faker->name(),
            'signer_document' => $this->faker->numerify('###.###.###-##'),
            'signer_email' => $this->faker->unique()->email(),
            'signer_phone' => $this->faker->phoneNumber(),
            'status' => 'pending',
            'expires_at' => now()->addDays(30),
        ];
    }

    public function signed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'signed',
                'signed_at' => now(),
                'signature_image_path' => 'signatures/test.png',
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
                'document_hash' => hash('sha256', $this->faker->uuid()),
                'geolocation' => [
                    'latitude' => $this->faker->latitude(-23.7, -23.4),
                    'longitude' => $this->faker->longitude(-46.8, -46.4),
                ],
            ];
        });
    }

    public function expired(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'expired',
                'expires_at' => now()->subDay(),
            ];
        });
    }

    public function canceled(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'canceled',
            ];
        });
    }
}

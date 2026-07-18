<?php

namespace Tests\Feature;

use App\Services\CepGeocodingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CepGeocodingServiceTest extends TestCase
{
    public function test_lookup_cep_returns_address_from_viacep(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response([
                'logradouro' => 'Praça da Sé',
                'bairro' => 'Sé',
                'localidade' => 'São Paulo',
                'uf' => 'SP',
            ]),
        ]);

        $result = (new CepGeocodingService)->lookupCep('01001-000');

        $this->assertSame('Praça da Sé - Sé', $result['address']);
        $this->assertSame('São Paulo', $result['city']);
        $this->assertSame('SP', $result['uf']);
    }

    public function test_lookup_cep_returns_null_when_viacep_reports_not_found_as_boolean(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response(['erro' => true]),
        ]);

        $this->assertNull((new CepGeocodingService)->lookupCep('00000-000'));
    }

    /**
     * Regressao: a API real do ViaCEP devolve "erro" como STRING "true",
     * nao boolean -- um === estrito contra true nunca disparava pra esse
     * caso real, entao "CEP nao encontrado" nunca era detectado de verdade.
     */
    public function test_lookup_cep_returns_null_when_viacep_reports_not_found_as_string(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response(['erro' => 'true']),
        ]);

        $this->assertNull((new CepGeocodingService)->lookupCep('00000-000'));
    }

    public function test_lookup_cep_returns_null_for_malformed_cep(): void
    {
        Http::fake();

        $this->assertNull((new CepGeocodingService)->lookupCep('123'));
        Http::assertNothingSent();
    }

    public function test_geocode_address_returns_coordinates_from_nominatim(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '-23.5505199', 'lon' => '-46.6333094'],
            ]),
        ]);

        $result = (new CepGeocodingService)->geocodeAddress('Praça da Sé, São Paulo - SP');

        $this->assertEqualsWithDelta(-23.5505199, $result['latitude'], 0.0001);
        $this->assertEqualsWithDelta(-46.6333094, $result['longitude'], 0.0001);
    }

    public function test_geocode_address_returns_null_when_nominatim_finds_nothing(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([]),
        ]);

        $this->assertNull((new CepGeocodingService)->geocodeAddress('Endereço inexistente 999999'));
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\SalesLeadResource\Pages\EditSalesLead;
use App\Filament\Central\Widgets\SalesLeadMapWidget;
use App\Models\SalesLead;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SalesLeadMapGeocodingPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_filling_cep_geocodes_address_and_lead_appears_on_the_map(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response([
                'logradouro' => 'Praça da Sé',
                'bairro' => 'Sé',
                'localidade' => 'São Paulo',
                'uf' => 'SP',
            ]),
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '-23.5505199', 'lon' => '-46.6333094'],
            ]),
        ]);

        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);

        $lead = SalesLead::create([
            'company_name' => 'Empresa Mapa CEP',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar',
            'source' => SalesLead::SOURCE_SITE,
        ]);

        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        // Simula o blur no campo CEP -- dispara afterStateUpdated igual um
        // usuario real preenchendo o formulario.
        Livewire::test(EditSalesLead::class, ['record' => $lead->getKey()])
            ->fillForm(['cep' => '01001-000'])
            ->assertHasNoFormErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $lead->refresh();

        $this->assertSame('Praça da Sé - Sé', $lead->address);
        $this->assertSame('São Paulo', $lead->city);
        $this->assertSame('SP', $lead->uf);
        $this->assertEqualsWithDelta(-23.5505199, (float) $lead->latitude, 0.0001);
        $this->assertEqualsWithDelta(-46.6333094, (float) $lead->longitude, 0.0001);

        // O mapa reflete o dado real recem-geocodificado, nao um snapshot
        // antigo -- confirma o pipeline completo, do CEP ate' o widget.
        $mapLeads = (new SalesLeadMapWidget)->getLeads();
        $mapEntry = collect($mapLeads)->firstWhere('id', $lead->id);

        $this->assertNotNull($mapEntry, 'Lead geocodificado não apareceu no Mapa Comercial.');
        $this->assertEqualsWithDelta(-23.5505199, (float) $mapEntry['latitude'], 0.0001);
        $this->assertEqualsWithDelta(-46.6333094, (float) $mapEntry['longitude'], 0.0001);
    }

    public function test_lead_without_coordinates_does_not_appear_on_the_map(): void
    {
        SalesLead::create([
            'company_name' => 'Empresa Sem Endereço',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar',
            'source' => SalesLead::SOURCE_SITE,
        ]);

        $mapLeads = (new SalesLeadMapWidget)->getLeads();

        $this->assertCount(0, $mapLeads);
    }
}

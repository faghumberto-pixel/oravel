<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\SalesLeadResource\Pages\EditSalesLead;
use App\Models\SalesLead;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesLeadMultiValueFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_multiple_decision_makers_segments_and_sources(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);

        $lead = SalesLead::create([
            'company_name' => 'Empresa Multi Valor',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar',
            'source' => SalesLead::SOURCE_SITE,
        ]);

        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        Livewire::test(EditSalesLead::class, ['record' => $lead->getKey()])
            ->fillForm([
                'decision_makers' => [
                    ['name' => 'Ana Diretora', 'role' => 'Diretora Financeira'],
                    ['name' => 'Bruno Gerente', 'role' => 'Gerente de Operações'],
                ],
                'additional_segments' => [
                    ['segment' => 'Agronegócio'],
                    ['segment' => 'Eventos'],
                ],
                'additional_sources' => [
                    ['source' => 'Feira do Setor'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $lead->refresh();

        $this->assertCount(2, $lead->decision_makers);
        $this->assertSame('Ana Diretora', $lead->decision_makers[0]['name']);
        $this->assertSame('Diretora Financeira', $lead->decision_makers[0]['role']);

        $this->assertSame(['Agronegócio', 'Eventos'], $lead->additional_segments);
        $this->assertSame(['Feira do Setor'], $lead->additional_sources);

        // segment/source PRIMARIOS continuam intactos -- Kanban/dashboards/
        // mapa dependem desses, nao podem ter sido afetados pelos repeaters.
        $this->assertSame('industrial_hospitalar', $lead->segment);
        $this->assertSame(SalesLead::SOURCE_SITE, $lead->source);
    }

    public function test_converting_to_tenant_defaults_admin_name_from_first_decision_maker(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);

        $lead = SalesLead::create([
            'company_name' => 'Empresa Converter',
            'pipeline_stage' => SalesLead::STAGE_PROPOSTA_ENVIADA,
            'segment' => 'industrial_hospitalar',
            'source' => SalesLead::SOURCE_SITE,
            'email' => 'contato@empresaconverter.com.br',
            'estimated_contract_value' => 5000,
            'decision_makers' => [
                ['name' => 'Carla Presidente', 'role' => 'CEO'],
            ],
            'critical_pain' => 'Sem controle de manutenção',
        ]);

        $this->assertSame('Carla Presidente', $lead->primaryDecisionMaker()['name']);
    }
}

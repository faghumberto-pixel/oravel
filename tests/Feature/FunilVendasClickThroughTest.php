<?php

namespace Tests\Feature;

use App\Filament\Central\Pages\FunilVendas;
use App\Models\SalesLead;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunilVendasClickThroughTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_stage_row_links_to_the_filtered_lead_list(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        $leadProspeccao = SalesLead::create([
            'company_name' => 'Empresa Prospecção Click', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
        ]);
        $leadGanho = SalesLead::create([
            'company_name' => 'Empresa Ganho Click', 'pipeline_stage' => SalesLead::STAGE_GANHO,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
        ]);

        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $page = app(FunilVendas::class);
        $rows = $page->getFunnelStages();

        $prospeccaoRow = collect($rows)->firstWhere('stage', SalesLead::STAGE_PROSPECCAO);
        $this->assertNotNull($prospeccaoRow['url']);

        // segue o link de verdade, igual um clique no navegador
        $response = $this->get($prospeccaoRow['url']);
        $response->assertOk();
        $response->assertSee('Empresa Prospecção Click');
        $response->assertDontSee('Empresa Ganho Click');
    }
}

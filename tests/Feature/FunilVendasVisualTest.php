<?php

namespace Tests\Feature;

use App\Filament\Central\Pages\FunilVendas;
use App\Models\SalesLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunilVendasVisualTest extends TestCase
{
    use RefreshDatabase;

    public function test_funnel_page_loads_and_last_stage_converges_to_a_point(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        SalesLead::create([
            'company_name' => 'Empresa Funil 1', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
        ]);
        SalesLead::create([
            'company_name' => 'Empresa Funil 2', 'pipeline_stage' => SalesLead::STAGE_GANHO,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
        ]);

        $this->actingAs($super);

        $response = $this->get('/central/funil-vendas');
        $response->assertOk();
        $response->assertSee('Taxa de Conversão');

        $page = app(FunilVendas::class);
        $rows = $page->getFunnelStages();
        $this->assertSame(0.0, end($rows)['bottomWidth']);
    }

    public function test_width_never_increases_even_when_a_middle_stage_has_more_raw_leads(): void
    {
        // Cenario que quebraria uma piramide invertida se a largura fosse
        // baseada na contagem bruta de cada estagio isolado: "contato
        // qualificado" tem MAIS leads brutos do que "prospeccao" (comum
        // numa esteira real, leads antigos se acumulam num estagio do
        // meio). A largura tem que ser cumulativa (estagio + tudo depois
        // dele) pra continuar so' diminuindo.
        for ($i = 0; $i < 2; $i++) {
            SalesLead::create([
                'company_name' => "Prospeccao $i", 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
                'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
            ]);
        }
        for ($i = 0; $i < 8; $i++) {
            SalesLead::create([
                'company_name' => "Qualificado $i", 'pipeline_stage' => SalesLead::STAGE_CONTATO_QUALIFICADO,
                'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
            ]);
        }
        SalesLead::create([
            'company_name' => 'Demonstracao 1', 'pipeline_stage' => SalesLead::STAGE_DEMONSTRACAO_REALIZADA,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
        ]);

        $page = app(FunilVendas::class);
        $rows = $page->getFunnelStages();

        $widths = array_column($rows, 'widthPercent');
        for ($i = 1; $i < count($widths); $i++) {
            $this->assertLessThanOrEqual($widths[$i - 1], $widths[$i], "Estagio {$i} mais largo que o anterior -- nao e' uma piramide invertida.");
        }
    }
}

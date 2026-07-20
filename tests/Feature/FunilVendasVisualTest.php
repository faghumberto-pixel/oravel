<?php

namespace Tests\Feature;

use App\Filament\Central\Pages\FunilVendas;
use App\Models\SalesLead;
use App\Models\User;
use Filament\Facades\Filament;
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

        Filament::setCurrentPanel(Filament::getPanel('central'));
        $page = app(FunilVendas::class);
        $rows = $page->getFunnelStages();
        $this->assertSame(0.0, end($rows)['bottomWidth']);
    }

    public function test_width_is_fixed_and_evenly_graduated_regardless_of_real_lead_distribution(): void
    {
        // Contagem BEM distorcida de proposito (contato qualificado tem
        // muito mais lead que prospeccao, comum numa esteira real) -- a
        // largura tem que continuar igual mesmo assim. Largura baseada em
        // contagem real (cumulativa) foi tentada antes e ficou ruim: com
        // atrito forte entre estagios, quase tudo colapsava perto do piso
        // minimo, so' o primeiro estagio ficava largo -- "layout pessimo",
        // feedback direto do usuario. Agora e' geometrico: primeiro
        // estagio = base (100%), ultimo = ponta (0%), sempre bem largo e
        // graduado, contagem real so' aparece como numero na faixa.
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

        Filament::setCurrentPanel(Filament::getPanel('central'));
        $page = app(FunilVendas::class);
        $rows = $page->getFunnelStages();

        // 5 estagios abertos (prospeccao..ganho): base 100%, degraus de
        // 20% ate' a ponta (0%) -- fixo, nao mexe com a distorcao acima.
        $this->assertSame(100.0, $rows[0]['topWidth']);
        $this->assertSame(80.0, $rows[0]['bottomWidth']);
        $this->assertSame(80.0, $rows[1]['topWidth']);
        $this->assertSame(0.0, end($rows)['bottomWidth']);

        $topWidths = array_column($rows, 'topWidth');
        for ($i = 1; $i < count($topWidths); $i++) {
            $this->assertLessThan($topWidths[$i - 1], $topWidths[$i], "Estagio {$i} nao ficou mais estreito que o anterior -- nao e' uma piramide invertida.");
        }
    }
}

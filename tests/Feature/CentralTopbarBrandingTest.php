<?php

namespace Tests\Feature;

use App\Models\SalesLead;
use App\Models\SalesLeadAppointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralTopbarBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        return $super;
    }

    public function test_brand_logo_r_is_the_fixed_orange_not_the_panel_primary_color(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->get('/central');
        $response->assertOk();

        // Central tem primary=Blue -- se o "r" dependesse de text-primary-500
        // sairia azul. Cor fixa da marca confirma que nao depende da cor
        // primaria de cada painel.
        $response->assertSee('color: #E8541A', false);
        // assertSeeText (nao assertSee) de proposito -- "Oravel" na resposta
        // crua vem quebrado por uma tag <span> no meio ("O<span>r</span>avel"),
        // so' aparece contiguo depois de remover as tags.
        $response->assertSeeText('Oravel');
    }

    public function test_topbar_shows_todays_appointment_count(): void
    {
        $lead = SalesLead::create([
            'company_name' => 'Empresa Compromisso Hoje', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
        ]);
        $lead->appointments()->create([
            'title' => 'Ligação de follow up',
            'type' => SalesLeadAppointment::TYPE_LIGACAO,
            'status' => SalesLeadAppointment::STATUS_PENDENTE,
            'scheduled_at' => now(),
        ]);
        // Compromisso de ONTEM nao pode contar no total de hoje.
        $lead->appointments()->create([
            'title' => 'Compromisso de ontem',
            'type' => SalesLeadAppointment::TYPE_LIGACAO,
            'status' => SalesLeadAppointment::STATUS_PENDENTE,
            'scheduled_at' => now()->subDay(),
        ]);

        $this->actingAs($this->superAdmin());

        $response = $this->get('/central');
        $response->assertOk();
        $response->assertSee('1');
        $response->assertSee('compromisso hoje');
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\SiteVisitResource\Pages\ListSiteVisits;
use App\Models\BlockedIp;
use App\Models\SiteVisit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Bloquear IP nao pode derrubar o acesso de quem bloqueia nem de usuarios logados (incidente 25/11/2026). */
class BloquearIpNaoBloqueiaProprioTest extends TestCase
{
    use DatabaseTransactions;

    private function visita(string $ip, ?string $userId = null): SiteVisit
    {
        return SiteVisit::create(['visitor_token' => 'v'.uniqid(), 'session_token' => 's'.uniqid(), 'ip_address' => $ip, 'user_id' => $userId, 'started_at' => now(), 'last_activity_at' => now(), 'entry_panel' => 'public', 'landing_path' => '/']);
    }

    public function test_regra_recusa_o_proprio_ip_e_ip_de_usuario_logado_e_aceita_os_demais(): void
    {
        $this->assertNotNull(BlockedIp::motivoParaNaoBloquear('203.0.113.7', '203.0.113.7'));
        $this->assertNull(BlockedIp::motivoParaNaoBloquear('203.0.113.8', '203.0.113.7'));

        $u = User::factory()->create();
        $this->visita('198.51.100.5', $u->id);
        $this->assertNotNull(BlockedIp::motivoParaNaoBloquear('198.51.100.5', '203.0.113.7'), 'IP ja usado por usuario logado');
        $this->assertNull(BlockedIp::motivoParaNaoBloquear('198.51.100.6', '203.0.113.7'));
    }

    public function test_acao_da_central_nao_cria_bloqueio_para_o_proprio_ip_mas_bloqueia_um_ip_comum(): void
    {
        $admin = User::factory()->create();
        config(['oravel.super_admins' => [$admin->email]]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $alheio = $this->visita('192.0.2.50');
        $meu = $this->visita('127.0.0.1');

        Livewire::test(ListSiteVisits::class)->callTableAction('bloquearIp', $alheio, ['reason' => 'duvidoso']);
        $this->assertTrue(BlockedIp::where('ip_address', '192.0.2.50')->exists());

        // O proprio IP nem aparece na lista (e, por garantia, a acao tambem o recusaria).
        Livewire::test(ListSiteVisits::class)->assertCanNotSeeTableRecords(collect([$meu]));
        $this->assertNotNull(BlockedIp::motivoParaNaoBloquear('127.0.0.1', '127.0.0.1'));
    }

    public function test_lista_esconde_o_uso_da_equipe_e_mostra_visitantes_comuns(): void
    {
        $admin = User::factory()->create();
        config(['oravel.super_admins' => [$admin->email]]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $daEquipe = $this->visita('198.51.100.20', $admin->id);
        $comum = $this->visita('198.51.100.21');

        Livewire::test(ListSiteVisits::class)->assertCanSeeTableRecords([$comum])->assertCanNotSeeTableRecords([$daEquipe]);
    }

    public function test_uso_do_super_admin_nao_gera_visita_e_o_de_cliente_gera(): void
    {
        $admin = User::factory()->create();
        config(['oravel.super_admins' => [$admin->email]]);
        $antes = SiteVisit::count();

        $this->actingAs($admin)->get('/admin/login');
        $this->get('/central/login');
        $this->assertSame($antes, SiteVisit::count(), 'super admin e Central nao contam');

        auth()->logout();
        $this->get('/admin/login');
        $this->assertGreaterThan($antes, SiteVisit::count(), 'visitante comum continua contando');
    }
}

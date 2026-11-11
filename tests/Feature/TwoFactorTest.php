<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\UserResource\Pages\EditUser;
use App\Filament\Pages\Auth\TwoFactorPage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * 2FA obrigatorio nos paineis admin e central, com excecao individual
 * (users.two_factor_exempt) e comando de reset.
 * Rodar SEMPRE em banco descartavel (DatabaseTransactions, nunca RefreshDatabase).
 */
class TwoFactorTest extends TestCase
{
    use DatabaseTransactions;

    private const SUPER = 'dono@oravel.test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['oravel.super_admins' => [self::SUPER], 'oravel.require_2fa' => true]);
    }

    private function user(?string $email = null): User
    {
        $user = User::factory()->create(['email' => $email ?? fake()->unique()->safeEmail()]);
        $user->forceFill(['is_approved' => true])->save();

        return $user;
    }

    /** Ativa e confirma o 2FA; devolve [segredo, codigos de recuperacao]. */
    private function withTwoFactor(User $user, string $panel = 'admin'): array
    {
        Filament::setCurrentPanel(Filament::getPanel($panel));

        $user->enableTwoFactorAuthentication();
        $user->confirmTwoFactorAuthentication();
        $user->refresh();

        $secret = decrypt($user->breezySession->two_factor_secret);
        $codes = $user->two_factor_recovery_codes;

        // zera a "sessao validada" que o confirm() deixou no teste atual
        session()->forget('breezy_session_id');

        return [$secret, $codes];
    }

    private function enterPage(User $user, string $panel = 'admin')
    {
        Filament::setCurrentPanel(Filament::getPanel($panel));
        $this->actingAs($user);

        return Livewire::test(TwoFactorPage::class);
    }

    public function test_login_sem_2fa_no_admin_so_alcanca_minha_conta(): void
    {
        $user = $this->user();

        // /admin e uma rota propria que so redireciona por perfil; testar paginas reais
        foreach (['/admin/painel-controle', '/admin/app-colaborador'] as $url) {
            $this->actingAs($user)->get($url)
                ->assertRedirect(route('filament.admin.pages.my-profile'));
        }
    }

    public function test_pular_o_2fa_no_painel_central_e_bloqueado(): void
    {
        $super = $this->user(self::SUPER);

        foreach (['/central', '/central/users', '/central/tenants'] as $url) {
            $this->actingAs($super)->get($url)
                ->assertRedirect(route('filament.central.pages.my-profile'));
        }
    }

    public function test_usuario_com_excecao_nao_e_forcado(): void
    {
        $user = $this->user();
        $user->forceFill(['two_factor_exempt' => true])->save();

        $resp = $this->actingAs($user)->get('/admin/painel-controle');

        $this->assertNotEquals(route('filament.admin.pages.my-profile'), $resp->headers->get('Location'));
        // 403 aqui seria o canAccess da pagina (usuario de teste sem papel/plano), que
        // roda DEPOIS do middleware de 2FA; o que importa e' nao ter sido redirecionado.
        $this->assertNotSame(302, $resp->getStatusCode());
    }

    public function test_desligamento_global_de_emergencia(): void
    {
        config(['oravel.require_2fa' => false]);

        $resp = $this->actingAs($this->user())->get('/admin/painel-controle');

        $this->assertNotEquals(route('filament.admin.pages.my-profile'), $resp->headers->get('Location'));
    }

    public function test_2fa_confirmado_sem_segundo_fator_vai_para_a_tela_do_codigo(): void
    {
        $user = $this->user();
        $this->withTwoFactor($user);

        $resp = $this->actingAs($user)->get('/admin/painel-controle');

        $this->assertStringContainsString('two-factor', (string) $resp->headers->get('Location'));
    }

    public function test_codigo_correto_libera(): void
    {
        $user = $this->user();
        [$secret] = $this->withTwoFactor($user);

        $this->enterPage($user)
            ->set('code', (new Google2FA)->getCurrentOtp($secret))
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertTrue($user->fresh()->hasValidTwoFactorSession());
    }

    public function test_codigo_errado_nao_libera(): void
    {
        $user = $this->user();
        $this->withTwoFactor($user);

        $this->enterPage($user)
            ->set('code', '000000')
            ->call('authenticate')
            ->assertHasErrors('code');

        $this->assertFalse($user->fresh()->hasValidTwoFactorSession());
    }

    public function test_codigo_de_recuperacao_libera_uma_unica_vez(): void
    {
        $user = $this->user();
        [, $codes] = $this->withTwoFactor($user);

        $this->enterPage($user)
            ->set('usingRecoveryCode', true)
            ->set('code', $codes[0])
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertTrue($user->fresh()->hasValidTwoFactorSession());
        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);

        session()->forget('breezy_session_id');

        $this->enterPage($user->fresh())
            ->set('usingRecoveryCode', true)
            ->set('code', $codes[0])
            ->call('authenticate')
            ->assertHasErrors('code');
    }

    public function test_segredo_fica_criptografado_no_banco(): void
    {
        $user = $this->user();
        [$secret, $codes] = $this->withTwoFactor($user);

        $raw = DB::table('breezy_sessions')->where('authenticatable_id', $user->id)->first();

        $this->assertStringNotContainsString($secret, $raw->two_factor_secret);
        $this->assertStringNotContainsString($codes[0], $raw->two_factor_recovery_codes);
    }

    public function test_comando_reset_remove_2fa_e_registra_sem_vazar_segredo(): void
    {
        $user = $this->user('perdeu@oravel.test');
        [$secret, $codes] = $this->withTwoFactor($user);

        $logged = [];
        Log::shouldReceive('warning')->andReturnUsing(function ($msg, $ctx = []) use (&$logged) {
            $logged[] = [$msg, $ctx];
        });

        $this->artisan('user:reset-2fa', ['email' => 'PERDEU@oravel.test', '--force' => true])
            ->assertSuccessful();

        $this->assertNull($user->fresh()->breezySession);
        $this->assertCount(1, $logged);
        $this->assertSame($user->email, $logged[0][1]['target_email']);

        $dump = json_encode($logged);
        $this->assertStringNotContainsString($secret, $dump);
        $this->assertStringNotContainsString($codes[0], $dump);
    }

    public function test_comando_reset_com_email_inexistente_falha(): void
    {
        $this->artisan('user:reset-2fa', ['email' => 'nao@existe.test', '--force' => true])
            ->assertFailed();
    }

    public function test_super_admin_define_excecao_na_central(): void
    {
        $super = $this->user(self::SUPER);
        $alvo = $this->user();

        Filament::setCurrentPanel(Filament::getPanel('central'));
        $this->actingAs($super);

        Livewire::test(EditUser::class, ['record' => $alvo->getRouteKey()])
            ->fillForm(['two_factor_exempt' => true, 'two_factor_exempt_reason' => 'Dispositivo compartilhado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $alvo->refresh();
        $this->assertTrue($alvo->two_factor_exempt);
        $this->assertSame('Dispositivo compartilhado', $alvo->two_factor_exempt_reason);
    }

    public function test_quem_nao_e_super_admin_nao_altera_a_excecao(): void
    {
        $comum = $this->user();
        $alvo = $this->user();

        Filament::setCurrentPanel(Filament::getPanel('central'));
        $this->actingAs($comum);

        try {
            Livewire::test(EditUser::class, ['record' => $alvo->getRouteKey()])
                ->set('data.two_factor_exempt', true)
                ->set('data.two_factor_exempt_reason', 'tentativa')
                ->call('save');
        } catch (\Throwable) {
            // acesso negado ao recurso tambem vale como bloqueio
        }

        $this->assertFalse((bool) $alvo->fresh()->two_factor_exempt);
    }

    public function test_exemption_nao_e_mass_assignable(): void
    {
        $user = $this->user();
        $user->fill(['two_factor_exempt' => true]);

        $this->assertFalse((bool) $user->two_factor_exempt);
    }
}

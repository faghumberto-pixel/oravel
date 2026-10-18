<?php

namespace Tests\Feature;

use App\Mail\OnboardingLembreteMail;
use App\Models\Client;
use App\Models\OnboardingLembrete;
use App\Models\PropostaComercial;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OnboardingLembretes;
use App\Support\Pacotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Lembretes do onboarding: regras por dia, idempotência, janela de horário e isolamento por empresa. */
class OnboardingLembretesTest extends TestCase
{
    use DatabaseTransactions;

    /** Terça-feira, 10h em São Paulo. */
    private function agora(int $diasDepois = 0, int $hora = 10): Carbon
    {
        return Carbon::parse('2026-10-20 '.$hora.':00:00', 'America/Sao_Paulo')->addDays($diasDepois);
    }

    private function empresa(int $criadaHaDias, string $pacote = 'servicos'): Tenant
    {
        $plano = Pacotes::planoDeVitrine($pacote);
        $plano->update(['is_active' => true]);
        $t = Tenant::create(['name' => 'Empresa '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $t->forceFill(['created_at' => $this->agora()->copy()->subDays($criadaHaDias)->subHours(2)])->save();
        $u = User::create(['name' => 'Admin', 'email' => 'adm-'.uniqid().'@empresa.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return $t;
    }

    private function rodar(?Carbon $agora = null, bool $ignorar = false): array
    {
        return app(OnboardingLembretes::class)->executar($agora ?? $this->agora(), $ignorar);
    }

    private function enviados(Tenant $t): array
    {
        return OnboardingLembrete::withoutGlobalScopes()->where('tenant_id', $t->id)->pluck('lembrete')->sort()->values()->all();
    }

    public function test_welcome_goes_out_on_day_zero_to_the_company_admins_with_the_checklist_link(): void
    {
        Mail::fake();
        $t = $this->empresa(0);
        $email = User::withoutGlobalScopes()->where('tenant_id', $t->id)->value('email');

        $r = $this->rodar();

        $this->assertSame(1, $r['boas_vindas']);
        Mail::assertSent(OnboardingLembreteMail::class, fn (OnboardingLembreteMail $m) => $m->hasTo($email) && str_contains($m->link, 'primeiros-passos') && str_contains((string) $m->linkAjuda, 'wa.me/5519999332615'));
    }

    public function test_each_reminder_is_sent_only_once_even_if_the_command_runs_again(): void
    {
        Mail::fake();
        $t = $this->empresa(0);

        $this->rodar();
        $this->rodar();
        $this->rodar($this->agora(0, 15));

        Mail::assertSent(OnboardingLembreteMail::class, 1);
        $this->assertSame(['boas_vindas'], $this->enviados($t));
    }

    public function test_day_two_reminds_about_step_one_only_while_it_is_pending(): void
    {
        Mail::fake();
        $parada = $this->empresa(2);
        $adiantada = $this->empresa(2);
        Client::create(['tenant_id' => $adiantada->id, 'name' => 'Já importou']);

        $r = $this->rodar();

        $this->assertSame(1, $r['passo_1']);
        $this->assertSame(['passo_1'], $this->enviados($parada));
        $this->assertSame([], $this->enviados($adiantada), 'quem já concluiu aquele ponto não recebe');
    }

    public function test_day_five_reminds_while_step_three_is_pending(): void
    {
        Mail::fake();
        $t = $this->empresa(5);
        Client::create(['tenant_id' => $t->id, 'name' => 'C']);

        $r = $this->rodar();

        $this->assertSame(1, $r['passo_3']);
        Mail::assertSent(OnboardingLembreteMail::class, fn (OnboardingLembreteMail $m) => str_contains(implode(' ', $m->paragrafos), 'Crie sua primeira proposta'));

        Mail::fake();
        $feita = $this->empresa(5);
        Client::create(['tenant_id' => $feita->id, 'name' => 'C']);
        PropostaComercial::withoutGlobalScopes()->create(['tenant_id' => $feita->id, 'status' => PropostaComercial::STATUS_ACEITA_PELO_CLIENTE]);
        $this->rodar();
        $this->assertSame([], $this->enviados($feita));
    }

    public function test_day_seven_asks_how_it_went_only_for_those_who_finished(): void
    {
        Mail::fake();
        $concluiu = $this->empresa(7);
        Client::create(['tenant_id' => $concluiu->id, 'name' => 'C']);
        PropostaComercial::withoutGlobalScopes()->create(['tenant_id' => $concluiu->id, 'status' => PropostaComercial::STATUS_ACEITA_PELO_CLIENTE]);
        $naoConcluiu = $this->empresa(7);

        $r = $this->rodar();

        $this->assertSame(1, $r['sete_dias']);
        $this->assertSame(['sete_dias'], $this->enviados($concluiu));
        $this->assertSame([], $this->enviados($naoConcluiu), 'quem não terminou não recebe a pergunta de como foi');
    }

    public function test_nothing_goes_out_on_weekends_or_outside_business_hours(): void
    {
        Mail::fake();
        $t = $this->empresa(0);

        $this->assertSame(0, array_sum($this->rodar($this->agora(0, 8))), 'antes das 9h');
        $this->assertSame(0, array_sum($this->rodar($this->agora(0, 18))), 'a partir das 18h');
        $this->assertSame(0, array_sum($this->rodar($this->agora(4, 10))), 'sábado');
        $this->assertSame([], $this->enviados($t));

        $this->assertSame(1, array_sum($this->rodar($this->agora(0, 17))), 'às 17h ainda vale');
    }

    public function test_the_window_uses_the_sao_paulo_clock_not_utc(): void
    {
        Mail::fake();
        $this->empresa(0);

        // 11h UTC = 8h em São Paulo: fora da janela; 12h UTC = 9h em São Paulo: dentro.
        $this->assertSame(0, array_sum($this->rodar(Carbon::parse('2026-10-20 11:00:00', 'UTC'))));
        $this->assertSame(1, array_sum($this->rodar(Carbon::parse('2026-10-20 12:00:00', 'UTC'))));
    }

    public function test_failed_send_is_retried_and_companies_without_onboarding_are_ignored(): void
    {
        Mail::fake();
        $this->empresa(0, 'estoque');
        $this->assertSame(0, array_sum($this->rodar()), 'Estoque ainda não tem onboarding');

        $t = $this->empresa(0);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp fora do ar'));
        $this->assertSame(0, array_sum($this->rodar()));
        $this->assertSame([], $this->enviados($t), 'sem registro: tenta de novo na próxima hora');
    }

    public function test_the_command_runs_and_reports(): void
    {
        Mail::fake();
        $this->empresa(0);

        $this->artisan('onboarding:lembretes --ignorar-janela')->expectsOutputToContain('boas_vindas=1')->assertSuccessful();
    }
}

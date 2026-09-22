<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Nr13Document;
use App\Models\Nr13Inspection;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Nr13DocumentExpiringNotification;
use App\Notifications\Nr13InspectionExpiringNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * Comando `nr13:check-expirations`: mesmo padrão de
 * App\Console\Commands\CheckEmployeeCertificationExpirations (30/15/7 dias + vencido/vencida,
 * com janela de 3 dias pra não renotificar pra sempre).
 *
 * DatabaseTransactions (e NÃO RefreshDatabase): config/database.php fixa 'default' => 'pgsql'.
 */
class Nr13ExpirationCommandTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenantWithUser(): array
    {
        $plan = Plan::create([
            'name' => 'Plano NR13 Cmd '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);
        $tenant = Tenant::create(['name' => 'Tenant '.uniqid(), 'slug' => 'nr13-cmd-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $user = User::create([
            'name' => 'U', 'email' => 'nr13cmd-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        return [$tenant, $user];
    }

    private function makeAsset(Tenant $tenant): Asset
    {
        return Asset::forceCreate(['tenant_id' => $tenant->id, 'name' => 'Caldeira', 'status' => Asset::STATUS_DISPONIVEL]);
    }

    public function test_notifica_documento_vencendo_em_30_15_e_7_dias(): void
    {
        NotificationFacade::fake();
        [$tenant, $user] = $this->makeTenantWithUser();
        $asset = $this->makeAsset($tenant);
        foreach ([30, 15, 7] as $dias) {
            Nr13Document::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'tipo' => Nr13Document::TIPO_CERTIFICADO_INSPECAO, 'data_validade' => now()->addDays($dias)]);
        }
        // fora das janelas -- não deve notificar
        Nr13Document::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'tipo' => Nr13Document::TIPO_CERTIFICADO_INSPECAO, 'data_validade' => now()->addDays(60)]);

        $this->artisan('nr13:check-expirations')->assertExitCode(0);

        NotificationFacade::assertSentTimes(Nr13DocumentExpiringNotification::class, 3);
        NotificationFacade::assertSentTo($user, Nr13DocumentExpiringNotification::class, fn ($n, $channels) => in_array('database', $channels) && in_array('mail', $channels));
    }

    public function test_notifica_documento_vencido_dentro_da_janela_de_3_dias_e_ignora_fora_dela(): void
    {
        NotificationFacade::fake();
        [$tenant, $user] = $this->makeTenantWithUser();
        $asset = $this->makeAsset($tenant);
        $recente = Nr13Document::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'tipo' => Nr13Document::TIPO_CERTIFICADO_INSPECAO, 'data_validade' => now()->subDay()]);
        $antigo = Nr13Document::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'tipo' => Nr13Document::TIPO_CERTIFICADO_INSPECAO, 'data_validade' => now()->subDays(10)]);

        $this->artisan('nr13:check-expirations');

        NotificationFacade::assertSentTimes(Nr13DocumentExpiringNotification::class, 1);
    }

    public function test_documento_sem_data_de_validade_nunca_notifica(): void
    {
        NotificationFacade::fake();
        [$tenant, $user] = $this->makeTenantWithUser();
        $asset = $this->makeAsset($tenant);
        Nr13Document::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'tipo' => Nr13Document::TIPO_PRONTUARIO, 'data_validade' => null]);

        $this->artisan('nr13:check-expirations');

        NotificationFacade::assertNothingSent();
    }

    public function test_notifica_inspecao_vencendo_e_vencida_pelo_mesmo_esquema(): void
    {
        NotificationFacade::fake();
        [$tenant, $user] = $this->makeTenantWithUser();
        $asset = $this->makeAsset($tenant);
        Nr13Inspection::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'tipo' => Nr13Inspection::TIPO_INTERNA, 'data_inspecao' => now()->subMonths(11), 'data_proxima_inspecao' => now()->addDays(7)]);
        Nr13Inspection::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'tipo' => Nr13Inspection::TIPO_EXTERNA, 'data_inspecao' => now()->subYear(), 'data_proxima_inspecao' => now()->subDay()]);

        $this->artisan('nr13:check-expirations');

        NotificationFacade::assertSentTimes(Nr13InspectionExpiringNotification::class, 2);
    }

    public function test_tenant_sem_usuarios_nao_gera_notificacao_mas_nao_quebra_o_comando(): void
    {
        NotificationFacade::fake();
        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets']]);
        $tenantSemUsuarios = Tenant::create(['name' => 'T'.uniqid(), 'slug' => 'nr13-sem-user-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $asset = $this->makeAsset($tenantSemUsuarios);
        Nr13Document::create(['tenant_id' => $tenantSemUsuarios->id, 'asset_id' => $asset->id, 'tipo' => Nr13Document::TIPO_CERTIFICADO_INSPECAO, 'data_validade' => now()->addDays(7)]);

        $this->artisan('nr13:check-expirations')->assertExitCode(0);

        NotificationFacade::assertNothingSent();
    }

    public function test_documentos_e_inspecoes_de_um_tenant_nao_notificam_usuarios_de_outro(): void
    {
        NotificationFacade::fake();
        [$tenantA, $userA] = $this->makeTenantWithUser();
        [$tenantB, $userB] = $this->makeTenantWithUser();
        $assetA = $this->makeAsset($tenantA);
        Nr13Document::create(['tenant_id' => $tenantA->id, 'asset_id' => $assetA->id, 'tipo' => Nr13Document::TIPO_CERTIFICADO_INSPECAO, 'data_validade' => now()->addDays(7)]);

        $this->artisan('nr13:check-expirations');

        NotificationFacade::assertSentTo($userA, Nr13DocumentExpiringNotification::class);
        NotificationFacade::assertNotSentTo($userB, Nr13DocumentExpiringNotification::class);
    }
}

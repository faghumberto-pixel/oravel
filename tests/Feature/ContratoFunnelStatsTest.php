<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource\Widgets\ContratoFunnelStats;
use App\Models\DocumentSignature;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SignatureService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cards de resumo no topo da tela de Contratos (pedido do usuário
 * 2026-09-23: "crie cards na parte superior com links para contratos
 * enviados, contratos assinados, contratos nao assinado, Pagos, Em
 * aberto").
 */
class ContratoFunnelStatsTest extends TestCase
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

    private function makeTenant(string $name, ?string $paymentStatus = null): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano '.$name, 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);

        return Tenant::create([
            'name' => $name, 'slug' => Str::slug($name).'-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
            'asaas_payment_status' => $paymentStatus,
        ]);
    }

    public function test_stats_count_signed_unsigned_paid_and_overdue_correctly(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $service = app(SignatureService::class);

        // 2 contratos assinados
        foreach (['Assinado Um', 'Assinado Dois'] as $name) {
            $tenant = $this->makeTenant($name, Tenant::PAYMENT_STATUS_EM_DIA);
            $link = $service->generateSignatureLink($tenant, ['name' => 'Admin', 'email' => 'a@a.com']);
            $token = basename(parse_url($link, PHP_URL_PATH));
            DocumentSignature::where('token', $token)->first()->markAsSigned();
        }

        // 1 contrato ainda não assinado
        $naoAssinado = $this->makeTenant('Nao Assinado', Tenant::PAYMENT_STATUS_ATRASADO);
        $service->generateSignatureLink($naoAssinado, ['name' => 'Admin', 'email' => 'b@b.com']);

        // Tenant pago extra sem passar pelo funil de assinatura (ex:
        // criado direto no banco) -- ainda deve contar pra "Pagos".
        $this->makeTenant('Pago Direto', Tenant::PAYMENT_STATUS_EM_DIA);

        $getStats = (new \ReflectionClass(ContratoFunnelStats::class))->getMethod('getStats');
        $getStats->setAccessible(true);
        $result = $getStats->invoke(new ContratoFunnelStats);

        $values = collect($result)->map(fn ($stat) => $stat->getValue())->values();

        // Enviados, Assinados, Não Assinados, Pagos, Em Aberto -- nesta ordem.
        $this->assertSame(3, $values[0], 'Enviados: 2 assinados + 1 nao assinado');
        $this->assertSame(2, $values[1], 'Assinados');
        $this->assertSame(1, $values[2], 'Nao assinados');
        $this->assertSame(3, $values[3], 'Pagos: 2 assinados em dia + 1 pago direto');
        $this->assertSame(1, $values[4], 'Em aberto: o nao assinado, que esta atrasado');
    }
}

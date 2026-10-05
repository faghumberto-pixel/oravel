<?php

namespace Tests\Feature;

use App\Filament\Resources\NotificationLogResource;
use App\Models\Employee;
use App\Models\EmployeeCertification;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Cpf;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido do usuario 05/10/2026: revisao do cadastro de colaboradores (CPF valido,
 * jornada 1-24h, nao excluir quem tem historico) + avisos financeiros so para quem o
 * tenant marcar + Auditoria de Notificacoes sem avisos da operacao do SaaS.
 */
class EmployeeAndFinancialNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $name): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_employees'],
        ]);

        return Tenant::create(['name' => $name.' '.uniqid(), 'slug' => Str::slug($name).'-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function makeUser(Tenant $tenant, array $extra = []): User
    {
        return User::create(array_merge([
            'name' => 'U '.uniqid(), 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id,
        ], $extra));
    }

    // ---- CPF ----

    public function test_cpf_validation_checks_digits_and_accepts_mask(): void
    {
        $this->assertTrue(Cpf::isValid('529.982.247-25'));
        $this->assertTrue(Cpf::isValid('52998224725'));
        $this->assertFalse(Cpf::isValid('52998224726'));
        $this->assertFalse(Cpf::isValid('11111111111'));
        $this->assertFalse(Cpf::isValid('123'));
        $this->assertSame('52998224725', Cpf::digits('529.982.247-25'));
    }

    public function test_cpf_rule_rejects_invalid_and_duplicates_in_same_tenant_only(): void
    {
        $a = $this->makeTenant('A');
        $b = $this->makeTenant('B');
        $existing = Employee::create(['tenant_id' => $a->id, 'name' => 'Fulano', 'cpf' => '52998224725']);

        $run = function (\Closure $rule, string $value): ?string {
            $error = null;
            $rule('cpf', $value, function (string $m) use (&$error) {
                $error = $m;
            });

            return $error;
        };

        $this->assertStringContainsString('inválido', $run(Cpf::rule($a->id), '52998224726'));
        $this->assertStringContainsString('Já existe', $run(Cpf::rule($a->id), '529.982.247-25'));
        $this->assertNull($run(Cpf::rule($a->id, $existing->id), '52998224725'));  // o proprio registro
        $this->assertNull($run(Cpf::rule($b->id), '52998224725'));                  // outro tenant pode ter o mesmo CPF
        $this->assertNull($run(Cpf::rule($a->id, $existing->id, '00000000001'), '00000000001') === null ? null : 'x'); // placeholder inalterado
    }

    // ---- exclusao com historico ----

    public function test_employee_with_history_cannot_be_deleted_but_clean_one_can(): void
    {
        $tenant = $this->makeTenant('C');
        $withHistory = Employee::create(['tenant_id' => $tenant->id, 'name' => 'Com historico', 'cpf' => '52998224725']);
        EmployeeCertification::create(['tenant_id' => $tenant->id, 'employee_id' => $withHistory->id, 'norma' => 'NR-35', 'data_validade' => now()->addYear()]);
        $clean = Employee::create(['tenant_id' => $tenant->id, 'name' => 'Sem historico', 'cpf' => '11144477735']);

        $this->assertTrue($withHistory->hasHistory());
        $this->assertFalse($withHistory->delete());
        $this->assertNotNull(Employee::withoutGlobalScopes()->find($withHistory->id));
        $this->assertSame(1, EmployeeCertification::withoutGlobalScopes()->where('employee_id', $withHistory->id)->count());

        $this->assertFalse($clean->hasHistory());
        $this->assertTrue((bool) $clean->delete());
    }

    // ---- avisos financeiros ----

    public function test_financial_recipients_are_only_the_chosen_ones_otherwise_the_legacy_rule(): void
    {
        $tenant = $this->makeTenant('D');
        $admin1 = $this->makeUser($tenant, ['role' => 'admin']);
        $admin2 = $this->makeUser($tenant, ['role' => 'admin']);
        $payer = $this->makeUser($tenant, ['role' => 'user']); // funcionario que paga
        $other = $this->makeUser($tenant, ['role' => 'user']);

        // Ninguem marcado: regra antiga (admins).
        $legacy = User::financialNotificationRecipients($tenant->id)->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$admin1->id, $admin2->id], $legacy);

        // Marca o funcionario que faz os pagamentos: so ele recebe.
        $payer->forceFill(['receives_financial_notifications' => true])->save();
        $chosen = User::financialNotificationRecipients($tenant->id)->pluck('id')->all();
        $this->assertSame([$payer->id], $chosen);
        $this->assertNotContains($admin1->id, $chosen);
        $this->assertNotContains($other->id, $chosen);

        // Outro tenant nao e afetado.
        $this->assertSame([], User::financialNotificationRecipients(null)->all());
    }

    // ---- Auditoria de Notificacoes ----

    private function notify(User $user, string $title, array $extra = []): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => 'Filament\\Notifications\\DatabaseNotification',
            'notifiable_type' => User::class, 'notifiable_id' => $user->id,
            'data' => json_encode(array_merge(['title' => $title, 'viewData' => []], $extra)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_notification_audit_hides_central_notices_and_follows_the_acting_tenant(): void
    {
        $topmix = $this->makeTenant('Topmix');
        $other = $this->makeTenant('Outro');
        $topUser = $this->makeUser($topmix);
        $otherUser = $this->makeUser($other);

        $email = 'super-'.uniqid().'@oravel.test';
        config(['oravel.super_admins' => [$email]]);
        $super = User::create(['name' => 'Super', 'email' => $email, 'password' => bcrypt('x')]);

        $this->notify($topUser, 'Conta vencendo');
        $this->notify($otherUser, 'Aviso do outro tenant');
        $this->notify($super, 'Implantação paga', ['viewData' => ['scope' => 'central']]);
        $this->notify($super, 'Checkout cancelado ou expirado (antigo, sem marca)', ['actions' => [['url' => 'https://app.oravel.com.br/central/tenants/x/edit']]]);

        $titles = fn () => NotificationLogResource::getEloquentQuery()->get()->map(fn ($n) => $n->data['title'])->all();

        // Super admin sem tenant escolhido: ve de todos, mas nunca os avisos da Central.
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $all = $titles();
        $this->assertContains('Conta vencendo', $all);
        $this->assertContains('Aviso do outro tenant', $all);
        $this->assertNotContains('Implantação paga', $all);
        $this->assertNotContains('Checkout cancelado ou expirado (antigo, sem marca)', $all);

        // Atuando como Topmix: so as notificacoes dos usuarios dele.
        session(['acting_tenant_id' => $topmix->id]);
        $this->assertSame(['Conta vencendo'], $titles());
    }
}

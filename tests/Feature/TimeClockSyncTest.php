<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TimeClock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TimeClockSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithUser(): array
    {
        $plan = Plan::create([
            'name' => 'Plano ponto '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_time_clocks'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant ponto '.uniqid(), 'slug' => 'tenant-ponto-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'features' => null,
        ]);
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $user = User::create([
            'name' => 'Usuário Ponto', 'email' => 'user-ponto-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('senha12345'), 'tenant_id' => $tenant->id, 'role' => 'admin',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole($role);

        return [$tenant, $user];
    }

    public function test_employee_can_sync_own_time_clock_batch(): void
    {
        [$tenant, $user] = $this->makeTenantWithUser();
        $employee = Employee::create([
            'tenant_id' => $tenant->id, 'user_id' => $user->id, 'name' => 'Colaborador Próprio', 'cpf' => '12345678901',
        ]);

        $clientUuid = (string) Str::uuid();

        $response = $this->actingAs($user)->postJson('/api/v1/time-clocks/sync', [
            'batidas' => [
                [
                    'client_uuid' => $clientUuid,
                    'employee_id' => $employee->id,
                    'tipo' => TimeClock::TIPO_ENTRADA,
                    'device_recorded_at' => now()->toISOString(),
                    'latitude' => -23.55,
                    'longitude' => -46.63,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['synced' => 1, 'failed' => 0]);

        $this->assertDatabaseHas('time_clocks', [
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'client_uuid' => $clientUuid,
            'sync_status' => TimeClock::SYNC_SYNCED,
        ]);
    }

    public function test_resyncing_the_same_client_uuid_does_not_duplicate(): void
    {
        [$tenant, $user] = $this->makeTenantWithUser();
        $employee = Employee::create([
            'tenant_id' => $tenant->id, 'user_id' => $user->id, 'name' => 'Colaborador Reenvio', 'cpf' => '23456789012',
        ]);

        $payload = [
            'batidas' => [[
                'client_uuid' => (string) Str::uuid(),
                'employee_id' => $employee->id,
                'tipo' => TimeClock::TIPO_ENTRADA,
                'device_recorded_at' => now()->toISOString(),
            ]],
        ];

        $this->actingAs($user)->postJson('/api/v1/time-clocks/sync', $payload)->assertOk();
        $this->actingAs($user)->postJson('/api/v1/time-clocks/sync', $payload)->assertOk();

        $this->assertSame(1, TimeClock::where('tenant_id', $tenant->id)->count());
    }

    public function test_employee_cannot_sync_another_employees_time_clock_without_supervisor_level(): void
    {
        [$tenant, $user] = $this->makeTenantWithUser();
        // $user não está vinculado a nenhum Employee -- não é o "próprio" e
        // não tem hierarchy_level de Supervisor em nenhum setor.
        $outroColaborador = Employee::create([
            'tenant_id' => $tenant->id, 'name' => 'Outro Colaborador', 'cpf' => '34567890123',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/time-clocks/sync', [
            'batidas' => [[
                'client_uuid' => (string) Str::uuid(),
                'employee_id' => $outroColaborador->id,
                'tipo' => TimeClock::TIPO_ENTRADA,
                'device_recorded_at' => now()->toISOString(),
            ]],
        ]);

        $response->assertOk();
        $response->assertJson(['synced' => 0, 'failed' => 1]);
        $this->assertDatabaseMissing('time_clocks', ['employee_id' => $outroColaborador->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\EmailMessage;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LimparRegistrosAntigosTest extends TestCase
{
    use DatabaseTransactions;

    public function test_apaga_so_o_que_e_antigo_e_automatico(): void
    {
        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $t = Tenant::create(['name' => 'E'.uniqid(), 'slug' => 'e-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $u = User::create(['name' => 'U', 'email' => uniqid().'@t.test', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true]);

        $nova = fn (string $criada, ?string $lida) => DB::table('notifications')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'x', 'notifiable_type' => User::class, 'notifiable_id' => $u->id,
            'data' => '{}', 'read_at' => $lida, 'created_at' => $criada, 'updated_at' => $criada,
        ]);
        $nova(now()->subDays(2)->toDateTimeString(), null);                                  // recente: fica
        $nova(now()->subDays(100)->toDateTimeString(), now()->subDays(90)->toDateTimeString()); // lida há 90 dias: sai
        $nova(now()->subDays(100)->toDateTimeString(), null);                                // antiga mas não lida (<180): fica
        $nova(now()->subDays(200)->toDateTimeString(), null);                                // +180: sai

        $email = function (string $origem, int $dias) use ($t, $u) {
            $m = EmailMessage::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'from_user_id' => $u->id, 'subject' => 'x', 'status' => 'enviado', 'origem' => $origem]);
            DB::table('email_messages')->where('id', $m->id)->update(['created_at' => now()->subDays($dias)]);

            return $m;
        };
        $email('sistema', 10);
        $velhoSistema = $email('sistema', 200);
        $velhoPessoa = $email('usuario', 200);

        $this->artisan('registros:limpar --dry-run')->assertSuccessful();
        $this->assertSame(4, DB::table('notifications')->where('notifiable_id', $u->id)->count(), 'simulação não apaga');

        $this->artisan('registros:limpar')->assertSuccessful();

        $this->assertSame(2, DB::table('notifications')->where('notifiable_id', $u->id)->count());
        $this->assertNull(EmailMessage::withoutGlobalScopes()->withTrashed()->find($velhoSistema->id));
        $this->assertNotNull(EmailMessage::withoutGlobalScopes()->withTrashed()->find($velhoPessoa->id), 'e-mail escrito por pessoa nunca é apagado');
        $this->assertSame(1, EmailMessage::withoutGlobalScopes()->where('tenant_id', $t->id)->where('origem', 'sistema')->count());
    }
}

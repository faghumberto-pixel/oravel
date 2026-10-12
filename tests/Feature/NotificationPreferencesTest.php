<?php

namespace Tests\Feature;

use App\Filament\Pages\MinhasNotificacoes;
use App\Models\Plan;
use App\Models\SalesLead;
use App\Models\SalesLeadAppointment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\NotificationCategories;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

/** Excluir avisos do sino + desligar categorias + fim do spam de "Compromisso vencido". */
class NotificationPreferencesTest extends TestCase
{
    use DatabaseTransactions;

    private function user(array $extra = []): User
    {
        $plan = Plan::create(['name' => 'Plano Pref '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $tenant = Tenant::create(['name' => 'Empresa Pref', 'slug' => 'pref-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $user = User::create(['name' => 'Pessoa', 'email' => 'pref-'.uniqid().'@teste.com', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $user->forceFill(['email_verified_at' => now(), 'is_approved' => true] + $extra)->save();

        return $user->refresh();
    }

    public function test_classifica_titulos_e_avarias_sao_obrigatorias(): void
    {
        $this->assertSame('compromissos', NotificationCategories::classify('Compromisso vencido: Ligar'));
        $this->assertSame('pendencias', NotificationCategories::classify('Nova pendência: OS 12'));
        $this->assertSame('avarias', NotificationCategories::classify('Avaria aguardando revisão do supervisor'));
        $this->assertSame('contrato_assinado', NotificationCategories::classify('Contrato assinado'));
        $this->assertSame('outros', NotificationCategories::classify('Qualquer coisa'));
        $this->assertArrayNotHasKey('avarias', NotificationCategories::optional());
    }

    public function test_categoria_desligada_nao_grava_o_aviso_mas_obrigatoria_sempre_chega(): void
    {
        $user = $this->user(['muted_notification_categories' => ['compromissos', 'avarias']]);

        Notification::make()->title('Compromisso vencido: Ligar')->sendToDatabase($user);
        Notification::make()->title('Nova pendência: OS 1')->sendToDatabase($user);
        Notification::make()->title('Avaria aguardando revisão do supervisor')->sendToDatabase($user);

        $titulos = $user->notifications()->get()->pluck('data.title')->sort()->values()->all();
        $this->assertSame(['Avaria aguardando revisão do supervisor', 'Nova pendência: OS 1'], $titulos);
    }

    public function test_pessoa_exclui_um_aviso_e_limpa_todos_so_os_proprios(): void
    {
        $user = $this->user();
        $outro = $this->user();
        Notification::make()->title('A')->sendToDatabase($user);
        Notification::make()->title('B')->sendToDatabase($user);
        Notification::make()->title('C')->sendToDatabase($outro);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('central'));
        $component = Livewire::test('database-notifications');
        $id = $user->notifications()->first()->id;

        $component->call('removeNotification', $id);
        $this->assertSame(1, $user->notifications()->count());

        $component->call('clearNotifications');
        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(1, $outro->notifications()->count(), 'não pode apagar aviso de outra pessoa');
    }

    public function test_compromisso_vencido_avisa_so_uma_vez(): void
    {
        $user = $this->user();
        $lead = SalesLead::create(['company_name' => 'Lead Pref']);
        $appt = SalesLeadAppointment::create([
            'sales_lead_id' => $lead->id, 'assigned_user_id' => $user->id, 'title' => 'Ligar',
            'scheduled_at' => now()->subHour(), 'status' => SalesLeadAppointment::STATUS_PENDENTE,
        ]);

        Artisan::call('sales:notify-appointments');
        $this->assertSame(1, $user->notifications()->count());

        // 20 min depois (passou da antiga janela de 15 min): não repete
        $appt->forceFill(['last_alerted_at' => now()->subMinutes(20)])->save();
        Artisan::call('sales:notify-appointments');
        $this->assertSame(1, $user->notifications()->count(), 'não pode repetir "vencido"');

        // reagendado para depois do último aviso e já vencido de novo: avisa 1x
        $appt->forceFill(['scheduled_at' => now()->subMinute(), 'last_alerted_at' => now()->subHours(3)])->save();
        Artisan::call('sales:notify-appointments');
        $this->assertSame(2, $user->notifications()->count());
    }

    public function test_tela_minhas_notificacoes_abre_e_salva_nos_dois_paineis(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        foreach (['admin' => MinhasNotificacoes::class, 'central' => \App\Filament\Central\Pages\MinhasNotificacoes::class] as $panel => $page) {
            Filament::setCurrentPanel(Filament::getPanel($panel));
            Livewire::test($page)
                ->assertSuccessful()
                ->set('data.receber', ['pendencias'])
                ->call('save');

            $muted = $user->refresh()->muted_notification_categories;
            $this->assertContains('compromissos', $muted);
            $this->assertNotContains('pendencias', $muted);
            $this->assertNotContains('avarias', $muted);
        }
    }
}

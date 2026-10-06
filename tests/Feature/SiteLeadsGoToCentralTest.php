<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\SalesLeadResource;
use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\Plan;
use App\Models\SalesLead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebPageview;
use App\Models\WebVisit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Leads do formulário do site da Oravel entram no funil de VENDAS da Central (SalesLead), com mensagem,
 * e-mail, cidade/estado e de onde vieram, e NUNCA aparecem para um tenant (06/10/2026).
 */
class SiteLeadsGoToCentralTest extends TestCase
{
    use DatabaseTransactions;

    private const TOKEN = 'token-canal-central';

    private Tenant $tenant;

    private User $super;

    private User $tenantAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_crm_leads']]);
        $this->tenant = Tenant::create(['name' => 'Oravel '.uniqid(), 'slug' => 'orv-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $email = 'dono-'.uniqid().'@oravel.test';
        config([
            'cache.default' => 'array',
            'oravel.super_admins' => [$email],
            'services.inbound.channels' => ['site-oravel' => ['token' => self::TOKEN, 'tenant_slug' => $this->tenant->slug, 'destination' => 'central']],
        ]);
        $this->super = User::create(['name' => 'Dono', 'email' => $email, 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $this->tenantAdmin = User::create(['name' => 'Outro', 'email' => 'o-'.uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true, 'role' => 'admin']);
    }

    private function visit(string $session, Carbon $start, Carbon $last, array $extra = []): WebVisit
    {
        $visit = WebVisit::create(array_merge([
            'visitor_token' => 'visitor-'.$session, 'session_token' => $session, 'city' => 'São José dos Campos', 'state' => 'SP',
            'device_type' => 'desktop', 'browser' => 'Chrome', 'os' => 'Windows', 'referrer_host' => 'google.com', 'landing_path' => '/',
            'started_at' => $start, 'last_activity_at' => $last, 'page_views' => 2, 'duration_seconds' => 120,
        ], $extra));
        foreach (['/', '/contato.php'] as $i => $path) {
            WebPageview::create(['web_visit_id' => $visit->id, 'page_token' => 'pg-'.$session.$i, 'path' => $path, 'entered_at' => $start->copy()->addSeconds($i * 20)]);
        }

        return $visit;
    }

    private function send(array $extra = [])
    {
        return $this->postJson('/api/inbound/leads', array_merge([
            'lead_id' => bin2hex(random_bytes(6)), 'origem' => 'contato-site', 'origem_label' => 'Contato do site',
            'name' => 'Leonardo Ribeiro', 'company' => 'Cristóvão Empilhadeiras', 'email' => 'leo@cristovao.com.br', 'phone' => '(12) 98261-3931',
            'segmento' => 'Não informado', 'porte' => 'Não informado', 'porte_label' => 'Porte',
            'mensagem' => 'Gostaríamos de uma apresentação do sistema.',
        ], $extra), ['X-Channel-Token' => self::TOKEN]);
    }

    public function test_lead_goes_to_the_central_funnel_with_message_place_and_origin_and_to_no_tenant(): void
    {
        $this->visit('session-ativa-1', now()->subMinutes(3), now()->subSeconds(5));

        $this->send()->assertStatus(201)->assertJsonPath('success', true);

        $lead = SalesLead::where('email', 'leo@cristovao.com.br')->firstOrFail();
        $this->assertSame('Cristóvão Empilhadeiras', $lead->company_name);
        $this->assertSame(SalesLead::SOURCE_SITE, $lead->source);
        $this->assertSame('Gostaríamos de uma apresentação do sistema.', $lead->inbound_message);
        $this->assertSame(['São José dos Campos', 'SP'], [$lead->city, $lead->uf]);
        $this->assertSame('Google (busca)', $lead->inbound_details['veio_de']);
        $this->assertSame('Leonardo Ribeiro', $lead->decision_makers[0]['name']);
        $this->assertSame(1, $lead->interactions()->count());

        // Nada no CRM de nenhum tenant.
        $this->assertSame(0, CrmLead::withoutGlobalScopes()->where('email', 'leo@cristovao.com.br')->count());

        // Aviso só no sino da Central, só para o super admin, abrindo o lead na Central.
        $notice = $this->super->notifications()->firstOrFail();
        $this->assertSame('central', $notice->data['viewData']['scope']);
        $this->assertStringContainsString('/central/', json_encode($notice->data, JSON_UNESCAPED_SLASHES));
        $this->assertSame(0, $this->tenantAdmin->notifications()->count());
    }

    public function test_without_a_matching_visit_the_lead_is_still_saved_and_two_simultaneous_visits_are_not_guessed(): void
    {
        $this->send(['email' => 'sem-visita@x.com.br'])->assertStatus(201);
        $this->assertNull(SalesLead::where('email', 'sem-visita@x.com.br')->firstOrFail()->city);

        $this->visit('session-a-1', now()->subMinutes(2), now()->subSeconds(5));
        $this->visit('session-b-1', now()->subMinutes(1), now()->subSeconds(2), ['city' => 'Maracás', 'state' => 'BA']);
        $this->send(['email' => 'duas@x.com.br'])->assertStatus(201);
        $this->assertNull(SalesLead::where('email', 'duas@x.com.br')->firstOrFail()->city);
    }

    public function test_a_tenant_user_cannot_open_the_central_sales_funnel(): void
    {
        $this->actingAs($this->tenantAdmin);

        $this->assertNotSame(200, $this->get(SalesLeadResource::getUrl('index', panel: 'central'))->status());
    }

    public function test_move_command_brings_old_crm_leads_to_the_central_keeping_date_message_place_and_notice_link(): void
    {
        $at = Carbon::parse('2026-10-06 11:29:31');
        $lead = CrmLead::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Leonardo Ribeiro', 'company_name' => 'Cristóvão Empilhadeiras', 'email' => 'leo@cristovao.com.br',
            'phone' => '(12) 98261-3931', 'source' => 'site_contato-site', 'stage' => CrmLead::STAGE_NOVO, 'segment' => 'Não informado', 'company_size' => 'Não informado',
            'landing_url' => 'https://oravel.com.br/contato.php#form',
        ]);
        DB::table('crm_leads')->where('id', $lead->id)->update(['created_at' => $at, 'updated_at' => $at]);
        CrmLeadInteraction::create([
            'tenant_id' => $this->tenant->id, 'crm_lead_id' => $lead->id, 'user_id' => $this->super->id, 'channel' => 'Site — Contato', 'contact_date' => $at,
            'summary' => "Lead recebido pelo formulário do site. Segmento: Não informado | Porte: Não informado | WhatsApp: (12) 98261-3931\n\nMensagem do visitante:\nPrecisamos de uma apresentação.",
            'stage_at_time' => CrmLead::STAGE_NOVO,
        ]);
        $this->visit('session-antiga-1', $at->copy()->subMinutes(4), $at->copy()->addSeconds(14));
        $notificationId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notificationId, 'type' => 'Filament\\Notifications\\DatabaseNotification', 'notifiable_type' => User::class, 'notifiable_id' => $this->super->id,
            'data' => json_encode(['format' => 'filament', 'title' => 'Novo lead do site', 'actions' => [['url' => 'https://app.oravel.com.br/admin/crm-leads/'.$lead->id.'/edit']]]),
            'created_at' => $at, 'updated_at' => $at,
        ]);

        // Dry-run: nada muda.
        $this->artisan('leads:move-site-leads-to-central')->assertSuccessful();
        $this->assertNotNull(CrmLead::withoutGlobalScopes()->find($lead->id));
        $this->assertSame(0, SalesLead::where('email', 'leo@cristovao.com.br')->count());

        $this->artisan('leads:move-site-leads-to-central', ['--apply' => true])->assertSuccessful();

        $moved = SalesLead::where('email', 'leo@cristovao.com.br')->firstOrFail();
        $this->assertSame('2026-10-06 11:29:31', $moved->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('Precisamos de uma apresentação.', $moved->inbound_message);
        $this->assertSame(['São José dos Campos', 'SP'], [$moved->city, $moved->uf]);
        $this->assertSame('Google (busca)', $moved->inbound_details['veio_de']);
        $this->assertSame(1, $moved->interactions()->count());
        $this->assertNull(CrmLead::withoutGlobalScopes()->find($lead->id));
        $this->assertSame(0, CrmLeadInteraction::withoutGlobalScopes()->where('crm_lead_id', $lead->id)->count());

        $data = json_decode(DB::table('notifications')->where('id', $notificationId)->value('data'), true);
        $this->assertStringContainsString('/central/', $data['actions'][0]['url']);
        $this->assertSame('central', $data['viewData']['scope']);
    }
}

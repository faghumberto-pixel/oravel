<?php

namespace Tests\Feature;

use App\Filament\Pages\CrmRoteiro;
use App\Filament\Resources\CrmDespesaResource;
use App\Filament\Resources\CrmVeiculoResource\Pages\EditCrmVeiculo;
use App\Filament\Resources\CrmVeiculoResource\Pages\ListCrmVeiculos;
use App\Filament\Resources\CrmVeiculoResource\RelationManagers\UsoRelationManager;
use App\Filament\Resources\CrmVisitaResource;
use App\Filament\Widgets\CrmAgendaWidget;
use App\Filament\Widgets\CrmLeadMapWidget;
use App\Livewire\CrmVisitasMobile;
use App\Models\Client;
use App\Models\CrmConta;
use App\Models\CrmLead;
use App\Models\CrmVeiculo;
use App\Models\CrmVisita;
use App\Models\CrmVisitaDespesa;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CrmVisitaService;
use App\Support\CrmRota;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Fase 6B: visitas, veículos, despesas de visita e roteiro do dia. */
class CrmCampoVisitasTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(array $extra = []): Tenant
    {
        $features = ['tabela_crm_leads' => true, 'tabela_crm_contas' => true, 'tabela_crm_visitas' => true, 'tabela_crm_veiculos' => true, 'tabela_crm_despesas' => true] + $extra;
        $plan = Plan::create(['name' => 'Plano Campo '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);

        return Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function admin(Tenant $t): User
    {
        $u = User::create(['name' => 'Admin '.uniqid(), 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return $u;
    }

    private function vendedor(Tenant $t): User
    {
        $u = User::create(['name' => 'Vendedor '.uniqid(), 'email' => 'v-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'comercial', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        foreach (['crm_lead', 'crm_visita', 'crm_despesa', 'crm_veiculo', 'crm_conta'] as $slug) {
            foreach (['ler', 'criar', 'editar'] as $acao) {
                Permission::firstOrCreate(['name' => "{$acao}_{$slug}", 'guard_name' => 'web']);
                $u->givePermissionTo("{$acao}_{$slug}");
            }
        }

        return $u;
    }

    private function visita(Tenant $t, User $v, array $extra = []): CrmVisita
    {
        return CrmVisita::create($extra + ['tenant_id' => $t->id, 'user_id' => $v->id, 'agendada_para' => now()->setTime(10, 0), 'contato_nome' => 'Cliente X',
            'latitude' => -22.9056, 'longitude' => -47.0608]); // Campinas
    }

    // ---------------------------------------------------------------- rota

    public function test_route_helpers_compute_distance_nearest_neighbour_km_and_map_link(): void
    {
        // Campinas -> São Paulo: ~84 km em linha reta.
        $d = CrmRota::haversine(-22.9056, -47.0608, -23.5505, -46.6333);
        $this->assertEqualsWithDelta(84000, $d, 3000);

        $pontos = [
            'longe' => ['lat' => -23.5505, 'lng' => -46.6333],     // São Paulo
            'perto' => ['lat' => -22.9099, 'lng' => -47.0626],     // Campinas centro
            'semgeo' => ['lat' => null, 'lng' => null],
            'meio' => ['lat' => -23.2237, 'lng' => -46.9094],       // Jundiaí
        ];
        $this->assertSame(['perto', 'meio', 'longe', 'semgeo'], CrmRota::ordenar($pontos, ['lat' => -22.9056, 'lng' => -47.0608]));
        $this->assertSame(['semgeo'], CrmRota::ordenar(['semgeo' => ['lat' => null, 'lng' => null]]));

        $seq = [['lat' => -22.9, 'lng' => -47.0], ['lat' => -23.5, 'lng' => -46.6]];
        $this->assertGreaterThan(70, CrmRota::kmEstimados($seq));

        $muitas = array_map(fn ($i) => ['lat' => -22.0 - $i / 10, 'lng' => -47.0], range(0, 14));
        $link = CrmRota::linkGoogleMaps($muitas);
        $this->assertStringContainsString('origin=-22,-47', $link);
        $this->assertSame(9, substr_count(explode('waypoints=', $link)[1], '|') + 1, 'no máximo 9 paradas intermediárias');
        $this->assertNull(CrmRota::linkGoogleMaps([]));
    }

    // ---------------------------------------------------------------- serviço

    public function test_visit_inherits_address_and_coordinates_from_the_account(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        $conta = CrmConta::create(['tenant_id' => $t->id, 'nome' => 'Acme', 'endereco' => 'Rua A, 1', 'cidade' => 'Campinas', 'uf' => 'SP', 'latitude' => -22.9, 'longitude' => -47.06]);

        $visita = CrmVisita::create(['tenant_id' => $t->id, 'user_id' => $v->id, 'crm_conta_id' => $conta->id, 'agendada_para' => now()]);

        $this->assertSame('Rua A, 1', $visita->endereco);
        $this->assertEquals(-22.9, $visita->latitude);
        $this->assertSame('Acme', $visita->alvoNome());
    }

    public function test_checkin_records_location_distance_and_warns_when_far_or_without_location(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        $svc = app(CrmVisitaService::class);

        $perto = $svc->checkin($this->visita($t, $v), $v, -22.9057, -47.0609);
        $this->assertSame(CrmVisita::STATUS_EM_ANDAMENTO, $perto['visita']->status);
        $this->assertLessThan(50, $perto['visita']->checkin_distancia_m);
        $this->assertSame([], $perto['avisos']);

        $longe = $svc->checkin($this->visita($t, $v), $v, -23.5505, -46.6333);
        $this->assertGreaterThan(80000, $longe['visita']->checkin_distancia_m);
        $this->assertStringContainsString('km do endereço', $longe['avisos'][0], 'avisa mas registra');

        $sem = $svc->checkin($this->visita($t, $v), $v, null, null);
        $this->assertNull($sem['visita']->checkin_lat);
        $this->assertStringContainsString('sem localização', $sem['avisos'][0]);

        $this->expectException(\DomainException::class);
        $svc->checkin($perto['visita'], $v, 0.0, 0.0);   // já em andamento
    }

    public function test_checkout_validates_km_updates_vehicle_creates_interaction_and_is_not_repeatable(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        $svc = app(CrmVisitaService::class);
        $carro = CrmVeiculo::create(['tenant_id' => $t->id, 'placa' => 'abc-1d23', 'km_atual' => 10000]);
        $lead = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Lead', 'company_name' => 'Acme']);

        $visita = $svc->checkin($this->visita($t, $v, ['crm_lead_id' => $lead->id, 'crm_veiculo_id' => $carro->id]), $v, -22.9056, -47.0608)['visita'];
        $this->assertSame(10000, $visita->km_inicio, 'km inicial sugerido pelo veículo');

        try {
            $svc->checkout($visita, $v, ['resultado' => '']);
            $this->fail('resultado obrigatório');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('resultado', $e->errors());
        }

        try {
            $svc->checkout($visita, $v, ['resultado' => 'ok', 'km_fim' => 9000]);
            $this->fail('km final menor');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('km_fim', $e->errors());
        }

        $fim = $svc->checkout($visita, $v, ['resultado' => 'Fechamos proposta', 'km_fim' => 10042, 'proximo_passo' => 'Enviar contrato', 'proximo_contato' => now()->addDays(2)->toDateString()]);

        $this->assertSame(CrmVisita::STATUS_REALIZADA, $fim->status);
        $this->assertSame(42, $fim->kmRodados());
        $this->assertSame(10042, $carro->fresh()->km_atual);
        $this->assertSame('ABC1D23', $carro->fresh()->placa);
        $interacao = $lead->interactions()->first();
        $this->assertSame('presencial', $interacao->channel);
        $this->assertStringContainsString('Fechamos proposta', $interacao->summary);
        $this->assertSame(now()->addDays(2)->toDateString(), $lead->fresh()->next_followup_date->toDateString());

        $this->expectException(\DomainException::class);
        $svc->checkout($fim, $v, ['resultado' => 'de novo']);
    }

    public function test_vehicle_in_use_by_another_and_expired_documents_generate_warnings(): void
    {
        $t = $this->tenant();
        $a = $this->vendedor($t);
        $b = $this->vendedor($t);
        $svc = app(CrmVisitaService::class);
        $carro = CrmVeiculo::create(['tenant_id' => $t->id, 'placa' => 'XYZ9A87', 'km_atual' => 1, 'vencimento_licenciamento' => now()->subDay()]);

        $svc->checkin($this->visita($t, $a, ['crm_veiculo_id' => $carro->id]), $a, -22.9, -47.0);
        $r = $svc->checkin($this->visita($t, $b, ['crm_veiculo_id' => $carro->id]), $b, -22.9, -47.0);

        $textos = implode('|', $r['avisos']);
        $this->assertStringContainsString('em uso em outra visita', $textos);
        $this->assertStringContainsString('documento vencido', $textos);
        $this->assertSame('vencido', $carro->situacaoVencimentos());
        $this->assertSame('a_vencer', (new CrmVeiculo(['vencimento_seguro' => now()->addDays(10)]))->situacaoVencimentos());
        $this->assertNull((new CrmVeiculo(['vencimento_seguro' => now()->addDays(90)]))->situacaoVencimentos());
    }

    public function test_salesperson_cannot_touch_another_salespersons_visit_but_admin_can(): void
    {
        $t = $this->tenant();
        $a = $this->vendedor($t);
        $b = $this->vendedor($t);
        $admin = $this->admin($t);
        $visita = $this->visita($t, $a);
        $svc = app(CrmVisitaService::class);

        try {
            $svc->checkin($visita, $b);
            $this->fail('deveria negar');
        } catch (\DomainException $e) {
            $this->assertSame(CrmVisita::STATUS_AGENDADA, $visita->fresh()->status);
        }

        $this->assertSame(CrmVisita::STATUS_EM_ANDAMENTO, $svc->checkin($visita, $admin)['visita']->status);
        $outroTenant = $this->vendedor($this->tenant());
        $this->expectException(\DomainException::class);
        $svc->cancelar($this->visita($t, $a), $outroTenant);
    }

    // ---------------------------------------------------------------- visibilidade e despesas

    public function test_resources_scope_visits_and_expenses_to_the_owner_unless_see_all(): void
    {
        $t = $this->tenant();
        $outro = $this->tenant();
        $a = $this->vendedor($t);
        $b = $this->vendedor($t);
        $admin = $this->admin($t);
        $this->visita($t, $a);
        $this->visita($t, $b);
        $this->visita($outro, $this->vendedor($outro));
        foreach ([$a, $b] as $u) {
            CrmVisitaDespesa::create(['tenant_id' => $t->id, 'user_id' => $u->id, 'data' => now(), 'tipo' => 'pedagio', 'valor' => 10]);
        }

        $this->actingAs($a);
        $this->assertSame(1, CrmVisitaResource::getEloquentQuery()->count());
        $this->assertSame(1, CrmDespesaResource::getEloquentQuery()->count());

        $this->actingAs($admin);
        $this->assertSame(2, CrmVisitaResource::getEloquentQuery()->count(), 'admin vê todas do tenant, nunca as de outro');
        $this->assertSame(2, CrmDespesaResource::getEloquentQuery()->count());
    }

    public function test_expense_approval_flow_is_admin_only_and_proof_goes_to_private_disk(): void
    {
        Storage::fake('media_private');
        $t = $this->tenant();
        $v = $this->vendedor($t);
        $admin = $this->admin($t);
        $d = CrmVisitaDespesa::create(['tenant_id' => $t->id, 'user_id' => $v->id, 'data' => now(), 'tipo' => 'combustivel', 'valor' => 250.5, 'litros' => 40]);
        $d->addMedia(UploadedFile::fake()->image('nota.jpg'))->toMediaCollection('comprovante');

        $this->assertSame('media_private', $d->getFirstMedia('comprovante')->disk);
        $this->assertSame('pendente', $d->status);

        $this->actingAs($v);
        Livewire::test(CrmDespesaResource\Pages\ListCrmDespesas::class)->assertTableActionHidden('aprovar', $d);

        $this->actingAs($admin);
        Livewire::test(CrmDespesaResource\Pages\ListCrmDespesas::class)->assertTableActionVisible('aprovar', $d)->callTableAction('aprovar', $d);
        $d->refresh();
        $this->assertSame('aprovada', $d->status);
        $this->assertSame($admin->id, $d->aprovado_por_user_id);

        Livewire::test(CrmDespesaResource\Pages\ListCrmDespesas::class)->callTableAction('pagar', $d);
        $this->assertSame('paga', $d->fresh()->status);
    }

    public function test_pdf_settlement_respects_visibility_period_and_limit(): void
    {
        $t = $this->tenant();
        $a = $this->vendedor($t);
        $b = $this->vendedor($t);
        CrmVisitaDespesa::create(['tenant_id' => $t->id, 'user_id' => $a->id, 'data' => now(), 'tipo' => 'pedagio', 'valor' => 11, 'descricao' => 'Do A']);
        CrmVisitaDespesa::create(['tenant_id' => $t->id, 'user_id' => $b->id, 'data' => now(), 'tipo' => 'pedagio', 'valor' => 22, 'descricao' => 'Do B']);
        $qs = ['de' => now()->startOfMonth()->toDateString(), 'ate' => now()->toDateString()];

        $this->actingAs($a);
        $r = $this->get(route('crm.despesas.pdf', $qs));
        $r->assertOk();
        $this->assertStringStartsWith('%PDF', $r->streamedContent());

        $view = view('pdf.crm-despesas', ['porVendedor' => collect(['X' => collect([CrmVisitaDespesa::first()])]), 'total' => 400, 'exibidas' => 300, 'de' => $qs['de'], 'ate' => $qs['ate'], 'limite' => 300])->render();
        $this->assertStringContainsString('mostra só 300 de 400', $view);

        $this->get(route('crm.despesas.pdf', ['de' => $qs['ate'], 'ate' => $qs['de']]))->assertStatus(302); // intervalo inválido volta com erro
    }

    // ---------------------------------------------------------------- celular

    public function test_mobile_page_runs_the_field_flow_and_blocks_other_salespersons_visits(): void
    {
        Storage::fake('media_private');
        Storage::fake('local');
        $t = $this->tenant();
        $v = $this->vendedor($t);
        $outro = $this->vendedor($t);
        $minha = $this->visita($t, $v);
        $alheia = $this->visita($t, $outro);
        $this->actingAs($v);

        $page = Livewire::test(CrmVisitasMobile::class)->assertSee('Cliente X');
        $this->assertCount(1, $page->instance()->visitas, 'só as minhas');

        $page->call('checkin', $minha->id, -22.9056, -47.0608)->assertSet('erro', null);
        $this->assertSame('em_andamento', $minha->fresh()->status);

        $page->call('abrirDespesa', $minha->id)->set('despesaValor', '35.90')->set('despesaTipo', 'estacionamento')
            ->set('comprovante', UploadedFile::fake()->image('recibo.jpg'))->call('lancarDespesa');
        $despesa = CrmVisitaDespesa::where('crm_visita_id', $minha->id)->firstOrFail();
        $this->assertEquals(35.90, $despesa->valor);
        $this->assertTrue($despesa->hasMedia('comprovante'));

        $page->call('abrirFinalizar', $minha->id)->set('resultado', 'Gostaram')->call('finalizar')->assertSet('erro', null);
        $this->assertSame('realizada', $minha->fresh()->status);

        $this->expectException(ModelNotFoundException::class);
        $page->call('checkin', $alheia->id);
    }

    public function test_mobile_quick_visit_and_validation(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        $conta = CrmConta::create(['tenant_id' => $t->id, 'nome' => 'Conta Avulsa']);
        $this->actingAs($v);

        Livewire::test(CrmVisitasMobile::class)->call('salvarAvulsa')->assertSet('erro', 'Escolha uma conta ou informe quem está sendo visitado.')
            ->set('avulsaContaId', $conta->id)->call('salvarAvulsa')->assertSet('erro', null);

        $this->assertSame(1, CrmVisita::where('user_id', $v->id)->where('crm_conta_id', $conta->id)->count());
    }

    // ---------------------------------------------------------------- roteiro, agenda e mapa

    public function test_route_page_optimizes_orders_and_saves_and_moves(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        $a = $this->visita($t, $v, ['contato_nome' => 'SP', 'latitude' => -23.5505, 'longitude' => -46.6333, 'agendada_para' => now()->setTime(9, 0)]);
        $b = $this->visita($t, $v, ['contato_nome' => 'Campinas', 'latitude' => -22.9099, 'longitude' => -47.0626, 'agendada_para' => now()->setTime(10, 0)]);
        $c = $this->visita($t, $v, ['contato_nome' => 'Sem mapa', 'latitude' => null, 'longitude' => null, 'agendada_para' => now()->setTime(11, 0)]);
        $this->actingAs($v);

        $page = Livewire::test(CrmRoteiro::class)->set('origemLat', -22.9056)->set('origemLng', -47.0608)->call('otimizar');

        $this->assertSame([$b->id, $a->id, $c->id], $page->instance()->visitas->pluck('id')->all(), 'perto, longe, sem localização por último');
        $this->assertGreaterThan(60, $page->instance()->kmEstimados);
        $this->assertStringContainsString('google.com/maps/dir', $page->instance()->linkRota);

        $page->call('mover', $a->id, 'subir');
        $this->assertSame([$a->id, $b->id, $c->id], $page->instance()->visitas->pluck('id')->all());
    }

    public function test_agenda_shows_visits_only_for_the_salesperson_or_all_for_admin(): void
    {
        $t = $this->tenant();
        $a = $this->vendedor($t);
        $b = $this->vendedor($t);
        $admin = $this->admin($t);
        $this->visita($t, $a, ['contato_nome' => 'Visita da A']);
        $this->visita($t, $b, ['contato_nome' => 'Visita da B']);
        $janela = ['start' => now()->startOfMonth()->toIso8601String(), 'end' => now()->endOfMonth()->toIso8601String()];

        $titulos = fn (User $u) => collect((function () use ($u, $janela) {
            $this->actingAs($u);

            return Livewire::test(CrmAgendaWidget::class)->instance()->fetchEvents($janela);
        })())->pluck('title')->implode('|');

        $this->assertStringContainsString('Visita da A', $titulos($a));
        $this->assertStringNotContainsString('Visita da B', $titulos($a));
        $this->assertStringContainsString('Visita da B', $titulos($admin));
    }

    public function test_commercial_map_uses_accounts_and_only_shows_erp_clients_when_module_is_on(): void
    {
        $semErp = $this->tenant();
        $comErp = $this->tenant(['tabela_clients' => true]);
        $adminSem = $this->admin($semErp);
        $adminCom = $this->admin($comErp);
        CrmConta::create(['tenant_id' => $semErp->id, 'nome' => 'Conta no mapa', 'latitude' => -22.9, 'longitude' => -47.0]);
        Client::create(['tenant_id' => $semErp->id, 'name' => 'Cliente ERP escondido', 'latitude' => -22.8, 'longitude' => -47.1]);
        $vinculado = Client::create(['tenant_id' => $comErp->id, 'name' => 'Vinculado', 'latitude' => -22.7, 'longitude' => -47.2]);
        CrmConta::create(['tenant_id' => $comErp->id, 'nome' => 'Conta vinculada', 'latitude' => -22.7, 'longitude' => -47.2, 'client_id' => $vinculado->id]);
        Client::create(['tenant_id' => $comErp->id, 'name' => 'Cliente ERP solto', 'latitude' => -22.6, 'longitude' => -47.3]);

        $this->actingAs($adminSem);
        $this->assertSame(['Conta no mapa'], collect(Livewire::test(CrmLeadMapWidget::class)->instance()->getClients())->pluck('name')->all());

        $this->actingAs($adminCom);
        $this->assertEqualsCanonicalizing(['Conta vinculada', 'Cliente ERP solto'], collect(Livewire::test(CrmLeadMapWidget::class)->instance()->getClients())->pluck('name')->all());
    }

    public function test_every_field_screen_renders_for_admin_and_salesperson(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $v = $this->vendedor($t);
        $carro = CrmVeiculo::create(['tenant_id' => $t->id, 'placa' => 'AAA1B23', 'km_atual' => 5, 'vencimento_seguro' => now()->addDays(5)]);
        $visita = $this->visita($t, $v, ['crm_veiculo_id' => $carro->id]);
        $despesa = CrmVisitaDespesa::create(['tenant_id' => $t->id, 'user_id' => $v->id, 'crm_visita_id' => $visita->id, 'data' => now(), 'tipo' => 'pedagio', 'valor' => 9]);

        foreach ([$admin, $v] as $user) {
            $this->actingAs($user);
            Livewire::test(CrmVisitaResource\Pages\ListCrmVisitas::class)->assertSuccessful()->assertSee('Cliente X');
            Livewire::test(CrmVisitaResource\Pages\EditCrmVisita::class, ['record' => $visita->getKey()])->assertSuccessful();
            Livewire::test(CrmVisitaResource\RelationManagers\DespesasRelationManager::class, ['ownerRecord' => $visita, 'pageClass' => CrmVisitaResource\Pages\EditCrmVisita::class])->assertSuccessful()->assertSee('Pedágio');
            Livewire::test(CrmDespesaResource\Pages\ListCrmDespesas::class)->assertSuccessful();
            Livewire::test(CrmDespesaResource\Pages\EditCrmDespesa::class, ['record' => $despesa->getKey()])->assertSuccessful();
            Livewire::test(CrmRoteiro::class)->assertSuccessful();
        }

        $this->actingAs($admin);
        Livewire::test(ListCrmVeiculos::class)->assertSuccessful()->assertSee('AAA1B23')->assertSee('Vence em até 30 dias');
        Livewire::test(EditCrmVeiculo::class, ['record' => $carro->getKey()])->assertSuccessful();
        Livewire::test(UsoRelationManager::class, ['ownerRecord' => $carro, 'pageClass' => EditCrmVeiculo::class])->assertSuccessful()->assertSee('Cliente X');
        Livewire::test(CrmVisitaResource\Widgets\CrmVisitaStats::class)->assertSuccessful();
    }

    public function test_plan_gating_and_migration(): void
    {
        $plan = Plan::create(['name' => 'Só leads '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_crm_leads' => true]]);
        $semCampo = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $this->actingAs($this->admin($semCampo));
        $this->assertFalse(CrmVisitaResource::canViewAny());
        $this->assertFalse(CrmDespesaResource::canViewAny());

        $this->actingAs($this->admin($this->tenant()));
        $this->assertTrue(CrmVisitaResource::canViewAny());

        $migration = require base_path('database/migrations/2026_11_18_100000_create_crm_veiculos_visitas_despesas.php');
        $migration->down();
        foreach (['crm_veiculos', 'crm_visitas', 'crm_visita_despesas'] as $tabela) {
            $this->assertFalse(Schema::hasTable($tabela));
        }
        $migration->up();
        $this->assertTrue(Schema::hasTable('crm_visitas'));
    }
}

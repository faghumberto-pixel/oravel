<?php

namespace Tests\Feature;

use App\Filament\Pages\PatioAprovacoes;
use App\Livewire\RentalDispatchChecklistMobile;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\EquipmentMovement;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RentalDispatchApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeScenario(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Despacho '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_solicitacao_locacao', 'tabela_equipment_movements'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Despacho '.uniqid(), 'slug' => 'tenant-despacho-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Gestor Pátio', 'email' => 'gestor-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('senha-correta'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Despacho']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Guindastes']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Teste', 'status' => Asset::STATUS_DISPONIVEL]);

        $solicitacao = SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'customer_id' => $client->id,
            'category_id' => $category->id,
            'asset_id' => $asset->id,
            'data_saida_prevista' => now()->addWeek(),
            'status_comercial' => 'proposta_em_andamento',
        ]);

        return [$tenant, $admin, $asset, $solicitacao];
    }

    private function completeChecklist(User $admin, SolicitacaoLocacao $solicitacao): EquipmentMovement
    {
        $this->actingAs($admin);

        $component = Livewire::test(RentalDispatchChecklistMobile::class, ['solicitacaoLocacao' => $solicitacao]);
        $movement = $component->instance()->equipmentMovement;

        foreach ($movement->items as $item) {
            if ($item->requires_photo) {
                $item->update(['is_checked' => true]);
                $item->addMediaFromString('fake-photo-bytes')->usingFileName('foto.jpg')->toMediaCollection('photos');
                $item->update(['is_checked' => true]);
            } else {
                $item->update(['is_checked' => true]);
            }
        }

        Livewire::test(RentalDispatchChecklistMobile::class, ['solicitacaoLocacao' => $solicitacao])
            ->call('finalize');

        return $movement->fresh();
    }

    public function test_finalizing_full_checklist_goes_to_pending_approval_not_straight_to_concluded(): void
    {
        [$tenant, $admin, $asset, $solicitacao] = $this->makeScenario();

        $movement = $this->completeChecklist($admin, $solicitacao);

        $this->assertSame(EquipmentMovement::STATUS_AGUARDANDO_APROVACAO, $movement->status);
        $this->assertNotNull($movement->qr_token);
        $this->assertSame(Asset::STATUS_DISPONIVEL, $asset->fresh()->status, 'asset nao deve mudar de status so por completar o checklist');
    }

    public function test_qr_verification_page_shows_invalid_until_approved(): void
    {
        [$tenant, $admin, $asset, $solicitacao] = $this->makeScenario();
        $movement = $this->completeChecklist($admin, $solicitacao);

        $this->get(route('portaria.verificar', ['token' => $movement->qr_token]))
            ->assertOk()
            ->assertSee('AGUARDANDO LIBERAÇÃO TÉCNICA');
    }

    public function test_manager_cannot_approve_with_wrong_password(): void
    {
        [$tenant, $admin, $asset, $solicitacao] = $this->makeScenario();
        $movement = $this->completeChecklist($admin, $solicitacao);

        $this->actingAs($admin);
        Livewire::test(PatioAprovacoes::class)
            ->set('approvalPassword', 'senha-errada')
            ->call('approve', $movement->id);

        $this->assertSame(EquipmentMovement::STATUS_AGUARDANDO_APROVACAO, $movement->fresh()->status);
    }

    public function test_manager_approval_with_correct_password_releases_asset_and_qr(): void
    {
        [$tenant, $admin, $asset, $solicitacao] = $this->makeScenario();
        $movement = $this->completeChecklist($admin, $solicitacao);

        $this->actingAs($admin);
        Livewire::test(PatioAprovacoes::class)
            ->set('approvalPassword', 'senha-correta')
            ->call('approve', $movement->id);

        $movement->refresh();
        $this->assertSame(EquipmentMovement::STATUS_CONCLUIDO, $movement->status);
        $this->assertSame($admin->id, $movement->approved_by_user_id);
        $this->assertNotNull($movement->approved_at);
        $this->assertSame(Asset::STATUS_LOCADO, $asset->fresh()->status);

        $this->get(route('portaria.verificar', ['token' => $movement->qr_token]))
            ->assertOk()
            ->assertSee('LIBERADO PARA SAÍDA');
    }

    public function test_manager_can_reject_sending_it_back_to_operator(): void
    {
        [$tenant, $admin, $asset, $solicitacao] = $this->makeScenario();
        $movement = $this->completeChecklist($admin, $solicitacao);

        $this->actingAs($admin);
        Livewire::test(PatioAprovacoes::class)
            ->set('rejectReason', 'Foto do pneu ilegível, refazer.')
            ->call('reject', $movement->id);

        $movement->refresh();
        $this->assertSame(EquipmentMovement::STATUS_EM_ANDAMENTO, $movement->status);
        $this->assertSame('Foto do pneu ilegível, refazer.', $movement->rejected_reason);
    }

    public function test_pending_approvals_page_only_lists_movements_from_same_tenant(): void
    {
        [$tenantA, $adminA, $assetA, $solicitacaoA] = $this->makeScenario();
        [$tenantB, $adminB, $assetB, $solicitacaoB] = $this->makeScenario();

        $movementA = $this->completeChecklist($adminA, $solicitacaoA);
        $this->completeChecklist($adminB, $solicitacaoB);

        $this->actingAs($adminA);
        $pending = Livewire::test(PatioAprovacoes::class)->instance()->pending;

        $this->assertCount(1, $pending);
        $this->assertSame($movementA->id, $pending->first()->id);
    }
}

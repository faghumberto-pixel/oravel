# Cobertura de PMP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dar visibilidade e ação ao vínculo automático (já existente) entre Plano de Manutenção Preventiva e Ativo — uma tela nova que lista todos os ativos com status de cobertura de PMP e permite abrir a OS direto dali, e uma aba própria de execução do PMP dentro da Ordem de Serviço.

**Architecture:** Nova Filament Page (`CoberturaPmp`) no menu PMP, tabela sobre `Asset` com status calculado via `MaintenancePlan::applicableFor()`/`dueStatusForAsset()`/`projectedDueDates()` (métodos já existentes e testados, não alterados). Ação "Abrir OS" reaproveita o mesmo padrão de criação de `MaintenanceOrder` já usado em `PainelPmp::createOrderFromAlert()`. `MaintenanceOrderChecklistSnapshotObserver` ganha um segundo bloco que gera itens `checklist_type = 'pmp'` a partir dos planos vencidos/vencendo do ativo, populando uma nova aba "PMP" no `MaintenanceOrderResource` — réplica estrutural da aba "Vistoria / Checklist" já existente (mesmos componentes, filtro de `checklist_type` diferente).

**Tech Stack:** Laravel 12, Filament 3, PHPUnit (RefreshDatabase, SQLite em memória).

**Spec:** `docs/superpowers/specs/2026-08-29-cobertura-pmp-design.md`

## Global Constraints

- Sem migration nova — `checklist_type` já existe em `maintenance_order_checklists` e aceita qualquer string; o valor novo `'pmp'` é usado como está.
- Não alterar `MaintenancePlan::applicableFor()`, `dueStatusForAsset()`, `projectedDueDates()` — já funcionam corretamente (confirmado pelo usuário), o trabalho é só construir UI em cima.
- Múltiplos planos vencidos do mesmo ativo entram todos na mesma `MaintenanceOrder` — nunca criar uma OS por plano.
- "Vencendo" = `MaintenancePlan::projectedDueDates($asset, 0)` retorna ao menos uma entrada (equivale a `month_offset === 0`, ou seja, vence ainda este mês) e o plano não está `is_overdue`.
- Cores de badge seguem a paleta Filament já usada em `Asset::statusColor()`: `success`/`warning`/`danger`/`gray`.
- Todos os testes deste plano usam `MaintenancePlan` com `checklist_group_id` preenchido (`isGroupTemplate() = true`). Nesse caso, `dueStatusForAsset()` ignora o campo `last_service_hours` gravado no `create()` e busca a última `PreventiveMaintenanceExecution` daquele Ativo+Plano — como nenhum teste cria uma execução, o valor efetivo usado é sempre `0.0` (nenhuma manutenção anterior registrada). Passar `last_service_hours` no `create()` dos testes é inofensivo mas não afeta o resultado — não é bug, é o comportamento correto do model.

---

## Task 1: Página "Cobertura de PMP" — esqueleto e cálculo de status

**Files:**
- Create: `app/Filament/Pages/CoberturaPmp.php`
- Create: `resources/views/filament/pages/cobertura-pmp.blade.php`
- Test: `tests/Feature/CoberturaPmpTest.php`

**Interfaces:**
- Consumes: `App\Models\Asset` (`checklist_group_id`, `horimetro_atual`, `client_id`, `tenant_id`), `App\Models\MaintenancePlan::applicableFor(Asset $asset)`, `->dueStatusForAsset(Asset $asset): array` (retorna `is_overdue`, `due_at_hours`, `due_at_date`), `->projectedDueDates(Asset $asset, int $months = 0): array` (retorna lista de `['month_offset' => int, ...]`).
- Produces: `CoberturaPmp::statusFor(Asset $asset): string` — retorna `'sem_grupo'|'em_dia'|'vencendo'|'vencido'`, usado pela Task 2 (ação "Abrir OS") e por testes.

- [ ] **Step 1: Escrever o teste de cálculo de status (4 casos)**

```php
<?php

namespace Tests\Feature;

use App\Filament\Pages\CoberturaPmp;
use App\Models\Asset;
use App\Models\ChecklistGroup;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoberturaPmpTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Cobertura PMP '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_maintenance_plans', 'tabela_assets'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Cobertura PMP '.uniqid(), 'slug' => 'tenant-cobertura-pmp-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin Cobertura PMP', 'email' => 'admin-cobertura-pmp-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_status_sem_grupo_quando_ativo_sem_grupo_e_sem_plano_proprio(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Sem Grupo', 'status' => Asset::STATUS_DISPONIVEL]);

        $this->assertSame('sem_grupo', CoberturaPmp::statusFor($asset));
    }

    public function test_status_em_dia_quando_plano_longe_do_vencimento(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Em Dia']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Em Dia', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 100,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 1000, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $this->assertSame('em_dia', CoberturaPmp::statusFor($asset));
    }

    public function test_status_vencido_quando_plano_ja_passou_do_horimetro(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Vencido']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Vencido', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 500,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $this->assertSame('vencido', CoberturaPmp::statusFor($asset));
    }

    public function test_status_vencendo_quando_plano_vence_dentro_do_mes_atual(): void
    {
        // Fixa "hoje" no dia 1 do mês -- sem isso o teste é flaky perto do
        // fim do mês real (projectedDueDates() joga a projeção de +5 dias
        // pro mês seguinte quando o teste roda, por exemplo, no dia 29).
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::create(2026, 3, 1));

        [$tenant] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Vencendo']);
        // Uso médio diário de 10h/dia (50h em 5 dias) -- faltam 50h pro
        // vencimento (1000-950), então projeta vencer em +5 dias, ainda
        // dentro do mês fixado acima.
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Vencendo', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 950,
        ]);
        \App\Models\HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'reading' => 900,
            'recorded_at' => now()->subDays(5), 'source' => 'manual',
        ]);
        \App\Models\HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'reading' => 950,
            'recorded_at' => now(), 'source' => 'manual',
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 1000, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $this->assertSame('vencendo', CoberturaPmp::statusFor($asset));

        \Illuminate\Support\Carbon::setTestNow();
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha (classe não existe)**

Run: `php artisan test tests/Feature/CoberturaPmpTest.php`
Expected: FAIL com `Class "App\Filament\Pages\CoberturaPmp" not found`

- [ ] **Step 3: Criar a Page com o método `statusFor()`**

```php
<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

/**
 * Dá visibilidade ao vínculo Plano de Manutenção <-> Ativo (já existente,
 * MaintenancePlan::applicableFor()) que hoje só aparecia como texto
 * informativo dentro do form de uma OS já criada (ver
 * MaintenanceOrderResource.php, Placeholder "Preventivas Sugeridas").
 * Ver docs/superpowers/specs/2026-08-29-cobertura-pmp-design.md.
 */
class CoberturaPmp extends Page
{
    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'PMP';

    protected static ?string $navigationLabel = 'Cobertura de PMP';

    protected static ?string $title = 'Cobertura de Manutenção Preventiva';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.cobertura-pmp';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    /**
     * sem_grupo: ativo sem grupo, ou grupo sem nenhum plano ativo aplicável.
     * vencido: algum plano aplicável com is_overdue=true.
     * vencendo: nenhum vencido, mas algum projectedDueDates($asset,0) não-vazio.
     * em_dia: nenhum vencido, nenhuma projeção pro mês atual.
     */
    public static function statusFor(Asset $asset): string
    {
        $plans = MaintenancePlan::applicableFor($asset)->where('is_active', true);

        if ($plans->isEmpty()) {
            return 'sem_grupo';
        }

        $temVencido = $plans->contains(fn (MaintenancePlan $plan) => $plan->dueStatusForAsset($asset)['is_overdue']);

        if ($temVencido) {
            return 'vencido';
        }

        $temVencendoEsteMes = $plans->contains(
            fn (MaintenancePlan $plan) => collect($plan->projectedDueDates($asset, 0))->isNotEmpty()
        );

        return $temVencendoEsteMes ? 'vencendo' : 'em_dia';
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'em_dia' => 'success',
            'vencendo' => 'warning',
            'vencido' => 'danger',
            default => 'gray',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'em_dia' => 'Em Dia',
            'vencendo' => 'Vencendo',
            'vencido' => 'Vencido',
            default => 'Sem Grupo',
        };
    }
}
```

- [ ] **Step 4: Criar a view mínima (será expandida na Task 2)**

```blade
<x-filament-panels::page>
    <div class="text-sm text-gray-500">
        A tabela de ativos com status de cobertura é implementada na Task 2.
    </div>
</x-filament-panels::page>
```

- [ ] **Step 5: Rodar o teste e confirmar que passa**

Run: `php artisan test tests/Feature/CoberturaPmpTest.php`
Expected: PASS (4 testes)

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Pages/CoberturaPmp.php resources/views/filament/pages/cobertura-pmp.blade.php tests/Feature/CoberturaPmpTest.php
git commit -m "feat: página Cobertura de PMP com cálculo de status por ativo"
```

---

## Task 2: Tabela de ativos com filtro e ação "Abrir OS"

**Files:**
- Modify: `app/Filament/Pages/CoberturaPmp.php`
- Test: `tests/Feature/CoberturaPmpTest.php`

**Interfaces:**
- Consumes: `CoberturaPmp::statusFor(Asset $asset)`, `statusColor()`, `statusLabel()` (Task 1); `App\Models\MaintenanceOrder::create()`, `App\Models\MaintenanceOrder::TYPE_PREVENTIVE`.
- Produces: `CoberturaPmp::abrirOs(string $assetId): ?string` — cria a `MaintenanceOrder`, retorna o `id` criado (usado pelo teste e pela action da tabela para redirecionar).

- [ ] **Step 1: Escrever o teste da ação "Abrir OS"**

Adicionar ao final de `tests/Feature/CoberturaPmpTest.php`:

```php
    public function test_abrir_os_cria_ordem_preventiva_vinculada_ao_ativo(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Abrir OS']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Abrir OS', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 500,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $this->actingAs($admin);

        $orderId = CoberturaPmp::abrirOs($asset->id);

        $order = \App\Models\MaintenanceOrder::findOrFail($orderId);
        $this->assertSame($asset->id, $order->asset_id);
        $this->assertSame(\App\Models\MaintenanceOrder::TYPE_PREVENTIVE, $order->maintenance_type);
        $this->assertSame('aguardando_diagnostico', $order->internal_status);
    }

    public function test_abrir_os_com_multiplos_planos_vencidos_cria_uma_unica_os(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Multiplos Planos']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Multiplos Planos', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 500,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0, 'is_active' => true,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Verificação de freios',
            'interval_hours' => 300, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $this->actingAs($admin);

        CoberturaPmp::abrirOs($asset->id);

        $this->assertSame(1, \App\Models\MaintenanceOrder::where('asset_id', $asset->id)->count());
    }
```

- [ ] **Step 2: Rodar os testes novos e confirmar que falham**

Run: `php artisan test tests/Feature/CoberturaPmpTest.php --filter=test_abrir_os`
Expected: FAIL com `Call to undefined method App\Filament\Pages\CoberturaPmp::abrirOs()`

- [ ] **Step 3: Implementar `abrirOs()` e a tabela na Page**

Adicionar ao `CoberturaPmp.php` (dentro da classe, junto aos métodos já criados na Task 1):

```php
    public static function abrirOs(string $assetId): ?string
    {
        $asset = Asset::find($assetId);

        if (! $asset) {
            return null;
        }

        $order = MaintenanceOrder::create([
            'tenant_id' => $asset->tenant_id,
            'asset_id' => $asset->id,
            'client_id' => $asset->client_id,
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => 'Aberto',
            'internal_status' => 'aguardando_diagnostico',
            'scheduled_at' => now(),
            'description' => 'Planejada via Cobertura de PMP.',
        ]);

        return $order->id;
    }

    public function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Asset::query();
    }

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')->label('Ativo')->searchable(),
                \Filament\Tables\Columns\TextColumn::make('patrimonio')->label('Patrimônio'),
                \Filament\Tables\Columns\TextColumn::make('checklistGroup.name')->label('Grupo')->placeholder('Sem grupo'),
                \Filament\Tables\Columns\TextColumn::make('pmp_status')
                    ->label('Status PMP')
                    ->badge()
                    ->state(fn (Asset $record) => static::statusLabel(static::statusFor($record)))
                    ->color(fn (Asset $record) => static::statusColor(static::statusFor($record))),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('pmp_status')
                    ->label('Status PMP')
                    ->options([
                        'sem_grupo' => 'Sem Grupo',
                        'em_dia' => 'Em Dia',
                        'vencendo' => 'Vencendo',
                        'vencido' => 'Vencido',
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        $ids = Asset::query()->get()->filter(
                            fn (Asset $asset) => static::statusFor($asset) === $data['value']
                        )->pluck('id');

                        return $query->whereIn('id', $ids);
                    }),
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('abrir_os')
                    ->label('Abrir OS')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->visible(fn (Asset $record) => in_array(static::statusFor($record), ['vencendo', 'vencido'], true))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Asset $record) => 'Cria uma OS preventiva para "'.$record->name.'" cobrindo todos os planos vencidos/vencendo, e já entra na Fila de Alocação de Técnicos.')
                    ->action(function (Asset $record) {
                        $orderId = static::abrirOs($record->id);

                        \Filament\Notifications\Notification::make()->title('OS criada')->success()->send();

                        return redirect(\App\Filament\Resources\MaintenanceOrderResource::getUrl('edit', ['record' => $orderId]));
                    }),
            ]);
    }
```

- [ ] **Step 4: Atualizar a view pra renderizar a tabela**

```blade
<x-filament-panels::page>
    {{ $this->table }}
</x-filament-panels::page>
```

Adicionar `use Filament\Tables\Concerns\InteractsWithTable;` e `use Filament\Tables\Contracts\HasTable;` na classe `CoberturaPmp` (implements `HasTable`, `use InteractsWithTable`).

- [ ] **Step 5: Rodar os testes e confirmar que passam**

Run: `php artisan test tests/Feature/CoberturaPmpTest.php`
Expected: PASS (6 testes)

- [ ] **Step 6: Rodar a suíte completa de PMP pra confirmar zero regressão**

Run: `php artisan test tests/Feature/PainelPmpTest.php tests/Feature/AlocacaoTecnicosPmpTest.php tests/Feature/CoberturaPmpTest.php`
Expected: PASS, todos os testes

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Pages/CoberturaPmp.php resources/views/filament/pages/cobertura-pmp.blade.php tests/Feature/CoberturaPmpTest.php
git commit -m "feat: tabela de ativos com filtro de status PMP e ação Abrir OS"
```

---

## Task 3: Snapshot de itens PMP na criação da OS

**Files:**
- Modify: `app/Observers/MaintenanceOrderChecklistSnapshotObserver.php`
- Test: `tests/Feature/MaintenanceOrderChecklistSnapshotFromPmpTest.php`

**Interfaces:**
- Consumes: `MaintenancePlan::applicableFor($asset)`, `->dueStatusForAsset($asset)`, `->projectedDueDates($asset, 0)` (já existentes).
- Produces: `MaintenanceOrderChecklist` rows com `checklist_type = 'pmp'` quando a OS é criada para um ativo com plano vencido/vencendo — consumido pela Task 4 (aba PMP no form).

- [ ] **Step 1: Escrever o teste do snapshot de itens PMP**

Adicionar ao final de `tests/Feature/MaintenanceOrderChecklistSnapshotFromPmpTest.php` (usa o `use App\Models\MaintenancePlan;` — adicionar ao topo do arquivo se ainda não importado):

```php
    public function test_new_order_gets_pmp_checklist_items_for_overdue_plans(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = \App\Models\ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo PMP Snapshot']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo PMP Snapshot', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 500,
        ]);
        \App\Models\MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Preventiva teste',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $pmpItems = $order->checklists()->where('checklist_type', 'pmp')->get();

        $this->assertCount(1, $pmpItems);
        $this->assertSame('Troca de óleo', $pmpItems->first()->item_name);
        $this->assertFalse($pmpItems->first()->is_completed);
    }

    public function test_new_order_gets_no_pmp_items_when_no_plan_is_overdue(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = \App\Models\ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Sem Vencimento']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Sem Vencimento', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 10,
        ]);
        \App\Models\MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 5000, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Corretiva teste',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $this->assertSame(0, $order->checklists()->where('checklist_type', 'pmp')->count());
    }

    public function test_new_order_gets_one_pmp_item_per_overdue_plan_when_multiple(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = \App\Models\ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Multiplos PMP']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Multiplos PMP', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 500,
        ]);
        \App\Models\MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0, 'is_active' => true,
        ]);
        \App\Models\MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Verificação de freios',
            'interval_hours' => 300, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Preventiva multipla',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $this->assertSame(2, $order->checklists()->where('checklist_type', 'pmp')->count());
    }
```

- [ ] **Step 2: Rodar os testes novos e confirmar que falham**

Run: `php artisan test tests/Feature/MaintenanceOrderChecklistSnapshotFromPmpTest.php --filter=test_new_order_gets`
Expected: FAIL — `test_new_order_gets_pmp_checklist_items_for_overdue_plans` falha com `assertCount(1, ...)` recebendo 0 (nenhum item `pmp` é gerado ainda)

- [ ] **Step 3: Estender o observer com o bloco de snapshot PMP**

Editar `app/Observers/MaintenanceOrderChecklistSnapshotObserver.php` — adicionar o import de `MaintenancePlan` no topo e o bloco novo dentro de `created()`, depois do loop existente de `$templates`:

```php
<?php

namespace App\Observers;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\MaintenancePlan;

/**
 * Ao criar uma OS, copia (snapshot -- nunca referencia direta) os itens de
 * checklist basico do Grupo do ativo + os itens extras especificos daquele
 * ativo, para a OS recem-criada. Edicao futura do template (grupo ou
 * ativo) nao afeta checklists ja gerados.
 *
 * Substitui a logica de checklist do antigo MaintenanceOrderObserver (nunca
 * registrado, referenciava App\Models\LogisticsQueue inexistente em outro
 * metodo) -- este observer so cuida do snapshot do checklist.
 *
 * Desde 2026-08-29 (ver docs/superpowers/specs/2026-08-29-cobertura-pmp-design.md)
 * tambem gera itens checklist_type='pmp' a partir dos Planos de Manutencao
 * vencidos/vencendo do ativo -- populam a aba "PMP" da OS
 * (MaintenanceOrderResource), execucao com toggle/observacao/foto igual a
 * aba "Vistoria / Checklist".
 */
class MaintenanceOrderChecklistSnapshotObserver
{
    public function created(MaintenanceOrder $order): void
    {
        $asset = $order->asset()->first();

        if (! $asset) {
            return;
        }

        $templates = collect();

        if ($asset->checklist_group_id) {
            $templates = $templates->concat(
                MaintenanceOrderChecklist::where('is_template', true)
                    ->where('checklist_group_id', $asset->checklist_group_id)
                    ->get()
            );
        }

        $templates = $templates->concat(
            MaintenanceOrderChecklist::where('is_template', true)
                ->where('asset_id', $asset->id)
                ->get()
        );

        foreach ($templates as $template) {
            MaintenanceOrderChecklist::create([
                'tenant_id' => $order->tenant_id,
                'maintenance_order_id' => $order->id,
                'category' => $template->category,
                'item_name' => $template->item_name,
                'instructions' => $template->instructions,
                'section' => $template->section,
                'checklist_type' => $order->maintenance_type ?: $template->checklist_type,
                'is_template' => false,
                'is_completed' => false,
            ]);
        }

        $this->snapshotPmpItems($order, $asset);
    }

    private function snapshotPmpItems(MaintenanceOrder $order, \App\Models\Asset $asset): void
    {
        $plans = MaintenancePlan::applicableFor($asset)->where('is_active', true);

        foreach ($plans as $plan) {
            $status = $plan->dueStatusForAsset($asset);
            $vencendoEsteMes = collect($plan->projectedDueDates($asset, 0))->isNotEmpty();

            if (! $status['is_overdue'] && ! $vencendoEsteMes) {
                continue;
            }

            MaintenanceOrderChecklist::create([
                'tenant_id' => $order->tenant_id,
                'maintenance_order_id' => $order->id,
                'item_name' => $plan->name,
                'instructions' => $plan->notes,
                'checklist_type' => 'pmp',
                'is_template' => false,
                'is_completed' => false,
            ]);
        }
    }
}
```

- [ ] **Step 4: Rodar os testes e confirmar que passam**

Run: `php artisan test tests/Feature/MaintenanceOrderChecklistSnapshotFromPmpTest.php`
Expected: PASS (5 testes — os 2 originais + os 3 novos)

- [ ] **Step 5: Verificar que o teste é load-bearing (reverter e confirmar falha)**

```bash
git stash push -- app/Observers/MaintenanceOrderChecklistSnapshotObserver.php
```

Run: `php artisan test tests/Feature/MaintenanceOrderChecklistSnapshotFromPmpTest.php --filter=test_new_order_gets_pmp_checklist_items_for_overdue_plans`
Expected: FAIL

```bash
git stash pop
```

Run novamente: `php artisan test tests/Feature/MaintenanceOrderChecklistSnapshotFromPmpTest.php`
Expected: PASS (confirma que o stash pop restaurou corretamente)

- [ ] **Step 6: Commit**

```bash
git add app/Observers/MaintenanceOrderChecklistSnapshotObserver.php tests/Feature/MaintenanceOrderChecklistSnapshotFromPmpTest.php
git commit -m "feat: snapshot de itens PMP (checklist_type=pmp) na criação da OS"
```

---

## Task 4: Aba "PMP" no formulário da Ordem de Serviço

**Files:**
- Modify: `app/Filament/Resources/MaintenanceOrderResource.php`
- Test: `tests/Feature/MaintenanceOrderChecklistSnapshotFromPmpTest.php`

**Interfaces:**
- Consumes: `MaintenanceOrderChecklist` rows com `checklist_type = 'pmp'` (Task 3), mesmos componentes Filament já usados na aba "Vistoria / Checklist" (`Forms\Components\Repeater`, `Forms\Components\ToggleButtons`, `Forms\Components\SpatieMediaLibraryFileUpload`).
- Produces: nada consumido por outras tasks — última task do plano.

- [ ] **Step 1: Escrever o teste de renderização da aba PMP**

Adicionar ao final de `tests/Feature/MaintenanceOrderChecklistSnapshotFromPmpTest.php`:

```php
    public function test_edit_order_page_shows_pmp_tab_when_order_has_pmp_items(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = \App\Models\ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Aba PMP']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Aba PMP', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 500,
        ]);
        \App\Models\MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo PMP',
            'interval_hours' => 250, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Preventiva com aba PMP',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $this->actingAs($admin);

        Livewire::test(EditMaintenanceOrder::class, ['record' => $order->id])
            ->assertOk()
            ->assertSee('Troca de óleo PMP');
    }

    public function test_edit_order_page_hides_pmp_tab_when_order_has_no_pmp_items(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Sem PMP', 'status' => Asset::STATUS_DISPONIVEL]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Corretiva sem PMP',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $this->actingAs($admin);

        $this->assertSame(0, $order->checklists()->where('checklist_type', 'pmp')->count());

        Livewire::test(EditMaintenanceOrder::class, ['record' => $order->id])
            ->assertOk();
    }
```

- [ ] **Step 2: Rodar os testes novos e confirmar que falham**

Run: `php artisan test tests/Feature/MaintenanceOrderChecklistSnapshotFromPmpTest.php --filter=test_edit_order_page_shows_pmp_tab`
Expected: FAIL — `assertSee('Troca de óleo PMP')` não encontra o texto (aba ainda não existe)

- [ ] **Step 3: Adicionar a aba PMP no formulário**

Abrir `app/Filament/Resources/MaintenanceOrderResource.php`. Localizar a aba "Vistoria / Checklist" existente (`Forms\Components\Tabs\Tab::make('Vistoria / Checklist')`, por volta da linha 503) e adicionar uma nova `Tab` logo depois dela, dentro do mesmo array de tabs:

```php
                Forms\Components\Tabs\Tab::make('PMP')
                    ->visible(fn (?MaintenanceOrder $record) => $record && $record->checklists()->where('checklist_type', 'pmp')->exists())
                    ->schema([
                        Forms\Components\Repeater::make('pmp_items')
                            ->relationship('checklists', modifyQueryUsing: fn (Builder $query) => $query->where('checklist_type', 'pmp'))
                            ->label('Planos de Manutenção Preventiva aplicáveis')
                            ->itemLabel(fn (array $state): ?string => $state['item_name'])
                            ->schema([
                                Forms\Components\TextInput::make('item_name')->label('Plano')->disabled()->dehydrated(true),
                                Forms\Components\ToggleButtons::make('status')
                                    ->label('Conformidade')
                                    ->options(['conforme' => 'Conforme', 'nao_conforme' => 'Não Conforme', 'nao_aplicavel' => 'N/A'])
                                    ->colors(['conforme' => 'success', 'nao_conforme' => 'danger', 'nao_aplicavel' => 'gray'])
                                    ->inline(),
                                Forms\Components\TextInput::make('notes')->label('Observações / Evidência'),
                                Forms\Components\SpatieMediaLibraryFileUpload::make('photos')
                                    ->collection('photos')
                                    ->label('Foto')
                                    ->image()
                                    ->imageResizeMode('contain')
                                    ->imageResizeTargetWidth('1600')
                                    ->imageResizeTargetHeight('1600')
                                    ->imageResizeUpscale(false),
                            ])->columns(3)->disableItemCreation()->disableItemDeletion(),
                    ]),
```

**Nota de implementação:** confirmar que `MaintenanceOrder` já tem `use App\Models\MaintenancePlan;` desnecessário aqui (não é usado nesta task) — só os componentes `Forms\Components\*` já importados no topo do arquivo (mesmos usados pela aba Checklist existente, nenhum import novo necessário).

- [ ] **Step 4: Rodar os testes e confirmar que passam**

Run: `php artisan test tests/Feature/MaintenanceOrderChecklistSnapshotFromPmpTest.php`
Expected: PASS (7 testes — os 5 da Task 3 + os 2 novos)

- [ ] **Step 5: Rodar a suíte completa de Manutenção/PMP pra confirmar zero regressão**

Run: `php artisan test tests/Feature/PainelPmpTest.php tests/Feature/AlocacaoTecnicosPmpTest.php tests/Feature/CoberturaPmpTest.php tests/Feature/MaintenanceOrderChecklistSnapshotFromPmpTest.php`
Expected: PASS, todos os testes

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/MaintenanceOrderResource.php tests/Feature/MaintenanceOrderChecklistSnapshotFromPmpTest.php
git commit -m "feat: aba PMP na Ordem de Serviço, com toggle/observação/foto por plano"
```

---

## Self-Review (executado ao escrever este plano)

**Cobertura da spec:**
- Seção 1 (página Cobertura de PMP + Status PMP + ação Abrir OS) → Tasks 1 e 2.
- Seção 2.1 (estender snapshot observer) → Task 3.
- Seção 2.2 (aba PMP no form) → Task 4.
- Múltiplos planos vencidos na mesma OS → coberto por teste dedicado nas Tasks 2 e 3.
- Testes de status/gatilho/render → um teste por caso em cada task.

**Consistência de tipos:** `CoberturaPmp::statusFor()` retorna sempre uma das 4 strings (`sem_grupo`/`em_dia`/`vencendo`/`vencido`) em Task 1, consumida identicamente em `statusColor()`/`statusLabel()` (Task 1) e no filtro/coluna da tabela (Task 2) — sem divergência de nome entre tasks. `checklist_type = 'pmp'` é o mesmo literal usado em Task 3 (observer) e Task 4 (filtro do Repeater).

**Placeholders:** nenhum "TBD"/"implementar depois" — toda task tem código completo e testável.

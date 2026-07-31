<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Contract;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * API para pré-carregamento de dados offline do técnico de campo
 * Retorna: tarefas do dia, ativos relacionados, contratos, checklists
 */
class TechnicianTasksController extends Controller
{
    /**
     * GET /api/technician/tasks-of-day
     * Retorna lista de tarefas do técnico para pré-carregamento no IndexedDB
     */
    public function tasksOfDay(): JsonResponse
    {
        $userId = Auth::id();
        $tenantId = Auth::user()->tenant_id;

        // 1. Ordens de manutenção não concluídas
        $maintenanceOrders = MaintenanceOrder::query()
            ->where('technician_id', $userId)
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['Concluída', 'Cancelada'])
            ->with(['asset', 'asset.criticalityLevel', 'client', 'checklists'])
            ->get();

        // 2. Mobilizações pendentes
        $mobilizations = AssetMovement::query()
            ->where('technician_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('movement_type', 'mobilization')
            ->whereNotIn('sync_status', ['synced'])
            ->with(['asset', 'contract', 'contract.client'])
            ->get();

        // 3. Desmobilizações pendentes
        $demobilizations = AssetMovement::query()
            ->where('technician_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('movement_type', 'demobilization')
            ->whereNotIn('sync_status', ['synced'])
            ->with(['asset', 'contract', 'contract.client'])
            ->get();

        // Coletar todos os asset IDs únicos
        $assetIds = collect()
            ->merge($maintenanceOrders->pluck('asset_id'))
            ->merge($mobilizations->pluck('asset_id'))
            ->merge($demobilizations->pluck('asset_id'))
            ->unique();

        // 4. Carregar ativos completos
        $assets = Asset::query()
            ->whereIn('id', $assetIds)
            ->where('tenant_id', $tenantId)
            ->with(['criticalityLevel', 'group', 'internalUnit', 'client'])
            ->get();

        // 5. Carregar contratos relacionados
        $contractIds = collect()
            ->merge($mobilizations->pluck('contract_id'))
            ->merge($demobilizations->pluck('contract_id'))
            ->filter()
            ->unique();

        $contracts = Contract::query()
            ->whereIn('id', $contractIds)
            ->where('tenant_id', $tenantId)
            ->with(['client'])
            ->get();

        // 6. Carregar checklists templates (se houver)
        $checklistTemplates = MaintenanceOrderChecklist::query()
            ->whereIn('maintenance_order_id', $maintenanceOrders->pluck('id'))
            ->get();

        // 7. Formatar dados para IndexedDB
        $tasks = collect()
            ->merge($maintenanceOrders->map(fn ($o) => [
                'id' => $o->id,
                'type' => 'maintenance',
                'label' => 'MANUTENÇÃO',
                'task_id' => $o->id,
                'asset_id' => $o->asset_id,
                'asset_name' => $o->asset?->name,
                'patrimonio' => $o->asset?->patrimonio,
                'client_id' => $o->client_id,
                'client_name' => $o->client?->name,
                'criticality' => $o->asset?->criticalityLevel?->letter ?? 'C',
                'nature' => $o->natureza_do_servico ?? 'Interno',
                'cep' => $o->asset?->cep,
                'status' => $o->status,
                'created_at' => $o->created_at,
            ]))
            ->merge($mobilizations->map(fn ($m) => [
                'id' => $m->id,
                'type' => 'mobilization',
                'label' => 'MOBILIZAÇÃO',
                'task_id' => $m->id,
                'asset_id' => $m->asset_id,
                'asset_name' => $m->asset?->name,
                'patrimonio' => $m->asset?->patrimonio,
                'client_id' => $m->contract?->client_id,
                'client_name' => $m->contract?->client?->name,
                'criticality' => $m->asset?->criticalityLevel?->letter ?? 'C',
                'nature' => 'Externo',
                'cep' => $m->contract?->client?->cep,
                'status' => $m->sync_status,
                'created_at' => $m->created_at,
            ]))
            ->merge($demobilizations->map(fn ($d) => [
                'id' => $d->id,
                'type' => 'demobilization',
                'label' => 'DESMOBILIZAÇÃO',
                'task_id' => $d->id,
                'asset_id' => $d->asset_id,
                'asset_name' => $d->asset?->name,
                'patrimonio' => $d->asset?->patrimonio,
                'client_id' => $d->contract?->client_id,
                'client_name' => $d->contract?->client?->name,
                'criticality' => $d->asset?->criticalityLevel?->letter ?? 'C',
                'nature' => 'Retorno',
                'cep' => $d->contract?->client?->cep,
                'status' => $d->sync_status,
                'created_at' => $d->created_at,
            ]));

        return response()->json([
            'tasks' => $tasks->values(),
            'assets' => $assets->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'patrimonio' => $a->patrimonio,
                'cep' => $a->cep,
                'criticality' => $a->criticalityLevel?->letter,
                'group_id' => $a->group_id,
                'group_name' => $a->group?->name,
                'capacity' => $a->capacity,
                'capacity_unit' => $a->capacity_unit,
                'last_horimetro' => $a->last_horimetro,
            ]),
            'contracts' => $contracts->map(fn ($c) => [
                'id' => $c->id,
                'number' => $c->number,
                'client_id' => $c->client_id,
                'client_name' => $c->client?->name,
                'client_cep' => $c->client?->cep,
                'status' => $c->status,
            ]),
            'checklist_templates' => $checklistTemplates,
            'sync_timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * HEAD /api/health-check
     * Simples health check para testes de conectividade offline
     */
    public function healthCheck()
    {
        return response()->noContent();
    }
}


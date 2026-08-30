<?php

namespace App\Http\Controllers;

use App\Filament\Pages\CoberturaPmp;
use App\Filament\Resources\MaintenancePlanResource\Support\PlanStatus;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\CrmLead;
use App\Models\Department;
use App\Models\EquipmentDamage;
use App\Models\FleetMaintenancePlan;
use App\Models\FleetVehicle;
use App\Models\FreightCarrier;
use App\Models\FreightRecord;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Material;
use App\Models\PartsRequest;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\SolicitacaoLocacao;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class TablePrintController extends Controller
{
    public function show(string $token)
    {
        $payload = Cache::get("table-print:{$token}");

        if (! $payload) {
            abort(410, 'Este link de impressão expirou. Gere um novo pelo botão Imprimir.');
        }

        $model = $payload['model'];
        $records = $model::whereIn((new $model)->getKeyName(), $payload['ids'])->get();

        // 'report' identifica um layout de colunas próprio, fora do match
        // genérico por model (columnsFor()) -- usado quando a tela de
        // origem precisa de colunas calculadas (não uma coluna real do
        // banco) que não fazem sentido pras outras telas que imprimem o
        // mesmo model. Closures não podem viver no payload cacheado
        // (Cache::put serializa, Closure não é serializável), então são
        // montadas aqui, não em CoberturaPmp.
        if (($payload['report'] ?? null) === 'cobertura_pmp') {
            $columns = [
                ['label' => 'Patrimônio', 'value' => fn (Asset $r) => $r->patrimonio],
                ['label' => 'Ativo', 'value' => fn (Asset $r) => $r->name],
                ['label' => 'Grupo', 'value' => fn (Asset $r) => $r->checklistGroup?->name],
                ['label' => 'Status PMP', 'value' => fn (Asset $r) => CoberturaPmp::statusLabel(CoberturaPmp::statusFor($r))],
            ];
            $records->load('checklistGroup');
        } elseif (($payload['report'] ?? null) === 'planos_preventivos') {
            $columns = [
                ['label' => 'Patrimônio', 'value' => fn ($r) => $r->asset?->patrimonio],
                ['label' => 'Ativo / Grupo', 'value' => fn ($r) => $r->asset?->name ?? $r->checklistGroup?->name.' (grupo)'],
                ['label' => 'Item', 'value' => fn ($r) => $r->name],
                ['label' => 'Intervalo (h)', 'value' => fn ($r) => $r->interval_hours],
                ['label' => 'Intervalo (dias)', 'value' => fn ($r) => $r->interval_days],
                ['label' => 'Status', 'value' => fn ($r) => PlanStatus::label(PlanStatus::forPlan($r))],
            ];
            $records->load(['asset', 'checklistGroup']);
        } else {
            [$columns, $with] = $this->columnsFor($model);

            if ($with) {
                $records->load($with);
            }
        }

        return view('reports.table-print', [
            'titulo' => $payload['titulo'],
            'filtros' => $payload['filtros'],
            'columns' => $columns,
            'records' => $records,
            'generatedAt' => now(),
            'tenantName' => Tenancy::current()?->name,
        ]);
    }

    /**
     * Colunas exibidas na impressao por Model, espelhando as colunas reais
     * de cada tela (ver os Resources correspondentes). Centralizado aqui
     * (em vez de 1 metodo por Resource) para manter a impressao num unico
     * lugar de manutencao.
     *
     * @return array{0: array<int, array{label: string, value: \Closure}>, 1: array<int, string>}
     */
    private function columnsFor(string $model): array
    {
        return match ($model) {
            Material::class => [[
                ['label' => 'SKU', 'value' => fn ($r) => $r->sku],
                ['label' => 'Nome', 'value' => fn ($r) => $r->name],
                ['label' => 'Categoria', 'value' => fn ($r) => $r->category?->name],
                ['label' => 'Fornecedor', 'value' => fn ($r) => $r->supplier?->name],
                ['label' => 'Preço', 'value' => fn ($r) => 'R$ '.number_format((float) $r->price, 2, ',', '.')],
                ['label' => 'Estoque Atual', 'value' => fn ($r) => $r->current_stock],
                ['label' => 'Estoque Mínimo', 'value' => fn ($r) => $r->min_stock],
            ], ['category', 'supplier']],

            Supplier::class => [[
                ['label' => 'Nome', 'value' => fn ($r) => $r->name],
                ['label' => 'Documento', 'value' => fn ($r) => $r->document],
                ['label' => 'E-mail', 'value' => fn ($r) => $r->email],
                ['label' => 'Telefone', 'value' => fn ($r) => $r->phone],
            ], []],

            Asset::class => [[
                ['label' => 'Patrimônio', 'value' => fn ($r) => $r->patrimonio],
                ['label' => 'Equipamento', 'value' => fn ($r) => $r->name],
                ['label' => 'Categoria', 'value' => fn ($r) => $r->asset_category],
                ['label' => 'Status', 'value' => fn ($r) => $r->status],
                ['label' => 'Horímetro Atual', 'value' => fn ($r) => $r->last_horimetro],
            ], []],

            MaintenanceOrder::class => [[
                ['label' => 'Nº OS', 'value' => fn ($r) => $r->os_number],
                ['label' => 'Ativo', 'value' => fn ($r) => $r->asset?->name],
                ['label' => 'Status', 'value' => fn ($r) => $r->status],
                ['label' => 'Tipo', 'value' => fn ($r) => $r->maintenance_type],
                ['label' => 'Data', 'value' => fn ($r) => optional($r->created_at)->format('d/m/Y')],
            ], ['asset']],

            Client::class => [[
                ['label' => 'Razão Social', 'value' => fn ($r) => $r->name],
                ['label' => 'CNPJ', 'value' => fn ($r) => $r->document],
                ['label' => 'Cidade', 'value' => fn ($r) => $r->city],
                ['label' => 'UF', 'value' => fn ($r) => $r->state],
            ], []],

            Contract::class => [[
                ['label' => 'Nº Contrato', 'value' => fn ($r) => $r->contract_number],
                ['label' => 'Cliente', 'value' => fn ($r) => $r->client?->name],
                ['label' => 'Ativo', 'value' => fn ($r) => $r->asset?->name],
                ['label' => 'Status', 'value' => fn ($r) => $r->status],
                ['label' => 'Valor', 'value' => fn ($r) => 'R$ '.number_format((float) $r->price, 2, ',', '.')],
            ], ['client', 'asset']],

            PartsRequest::class => [[
                ['label' => 'Nº OS', 'value' => fn ($r) => $r->maintenanceOrder?->os_number],
                ['label' => 'Material', 'value' => fn ($r) => $r->material?->name],
                ['label' => 'Qtd.', 'value' => fn ($r) => $r->quantity],
                ['label' => 'Status', 'value' => fn ($r) => ucfirst($r->status)],
                ['label' => 'Custo', 'value' => fn ($r) => 'R$ '.number_format((float) $r->cost_at_time, 2, ',', '.')],
            ], ['maintenanceOrder', 'material']],

            EquipmentDamage::class => [[
                ['label' => 'Nº OS', 'value' => fn ($r) => $r->maintenanceOrder?->os_number],
                ['label' => 'Ativo', 'value' => fn ($r) => $r->asset?->name],
                ['label' => 'Severidade', 'value' => fn ($r) => $r->severity],
                ['label' => 'Status', 'value' => fn ($r) => $r->status],
                ['label' => 'Data', 'value' => fn ($r) => optional($r->created_at)->format('d/m/Y')],
            ], ['maintenanceOrder', 'asset']],

            FleetVehicle::class => [[
                ['label' => 'Placa', 'value' => fn ($r) => $r->placa],
                ['label' => 'Modelo', 'value' => fn ($r) => $r->modelo],
                ['label' => 'Status', 'value' => fn ($r) => $r->status],
                ['label' => 'Km Atual', 'value' => fn ($r) => $r->km_atual],
            ], []],

            FreightRecord::class => [[
                ['label' => 'Data', 'value' => fn ($r) => optional($r->data)->format('d/m/Y')],
                ['label' => 'Tipo', 'value' => fn ($r) => ucfirst($r->tipo)],
                ['label' => 'Origem', 'value' => fn ($r) => $r->origem],
                ['label' => 'Destino', 'value' => fn ($r) => $r->destino],
                ['label' => 'Valor', 'value' => fn ($r) => 'R$ '.number_format((float) $r->valor, 2, ',', '.')],
            ], []],

            FreightCarrier::class => [[
                ['label' => 'Transportadora', 'value' => fn ($r) => $r->nome],
                ['label' => 'Documento', 'value' => fn ($r) => $r->documento],
                ['label' => 'Contato', 'value' => fn ($r) => $r->contato_nome],
                ['label' => 'Telefone', 'value' => fn ($r) => $r->contato_telefone],
            ], []],

            FleetMaintenancePlan::class => [[
                ['label' => 'Veículo', 'value' => fn ($r) => $r->fleetVehicle?->placa],
                ['label' => 'Tipo de Serviço', 'value' => fn ($r) => $r->tipo_servico],
                ['label' => 'Intervalo (km)', 'value' => fn ($r) => $r->intervalo_km],
                ['label' => 'Intervalo (dias)', 'value' => fn ($r) => $r->intervalo_dias],
            ], ['fleetVehicle']],

            User::class => [[
                ['label' => 'Nome', 'value' => fn ($r) => $r->name],
                ['label' => 'E-mail', 'value' => fn ($r) => $r->email],
                ['label' => 'Departamento', 'value' => fn ($r) => $r->department?->name],
                ['label' => 'Função', 'value' => fn ($r) => $r->role],
            ], ['department']],

            SolicitacaoLocacao::class => [[
                ['label' => 'Cliente', 'value' => fn ($r) => $r->customer?->name],
                ['label' => 'Categoria', 'value' => fn ($r) => $r->category?->name],
                ['label' => 'Status', 'value' => fn ($r) => ucfirst(str_replace('_', ' ', $r->status_comercial))],
                ['label' => 'Saída Prevista', 'value' => fn ($r) => optional($r->data_saida_prevista)->format('d/m/Y')],
            ], ['customer', 'category']],

            MaintenancePlan::class => [[
                ['label' => 'Ativo / Grupo', 'value' => fn ($r) => $r->asset?->name ?? $r->checklistGroup?->name.' (grupo)'],
                ['label' => 'Nome do Plano', 'value' => fn ($r) => $r->name],
                ['label' => 'Intervalo (horas)', 'value' => fn ($r) => $r->interval_hours],
                ['label' => 'Ativo?', 'value' => fn ($r) => $r->is_active ? 'Sim' : 'Não'],
            ], ['asset', 'checklistGroup']],

            Role::class => [[
                ['label' => 'Função', 'value' => fn ($r) => $r->name],
                ['label' => 'Departamento', 'value' => fn ($r) => $r->department?->name],
                ['label' => 'Permissões', 'value' => fn ($r) => $r->permissions()->count()],
            ], ['department']],

            Department::class => [[
                ['label' => 'Departamento', 'value' => fn ($r) => $r->name],
                ['label' => 'Código', 'value' => fn ($r) => $r->code],
                ['label' => 'Funcionários', 'value' => fn ($r) => $r->users()->count()],
            ], []],

            UserActivityLog::class => [[
                ['label' => 'Data/Hora', 'value' => fn ($r) => optional($r->created_at)->format('d/m/Y H:i:s')],
                ['label' => 'Usuário', 'value' => fn ($r) => $r->user?->name],
                ['label' => 'Tela/Recurso', 'value' => fn ($r) => $r->resource_label],
                ['label' => 'Ação', 'value' => fn ($r) => match ($r->action) {
                    UserActivityLog::ACTION_VIEW => 'Visualizou',
                    UserActivityLog::ACTION_CREATED => 'Criou',
                    UserActivityLog::ACTION_UPDATED => 'Editou',
                    UserActivityLog::ACTION_DELETED => 'Excluiu',
                    UserActivityLog::ACTION_LOGIN => 'Login',
                    UserActivityLog::ACTION_LOGOUT => 'Logout',
                    default => $r->action ?? '—',
                }],
            ], ['user']],

            Quote::class => [[
                ['label' => 'Cliente', 'value' => fn ($r) => $r->client?->name],
                ['label' => 'Tipo', 'value' => fn ($r) => Quote::typeLabels()[$r->type] ?? $r->type],
                ['label' => 'Status', 'value' => fn ($r) => Quote::statusLabels()[$r->status] ?? $r->status],
                ['label' => 'Valor Total', 'value' => fn ($r) => 'R$ '.number_format((float) $r->total_value, 2, ',', '.')],
                ['label' => 'Responsável', 'value' => fn ($r) => $r->assignedUser?->name],
                ['label' => 'Criado em', 'value' => fn ($r) => optional($r->created_at)->format('d/m/Y')],
            ], ['client', 'assignedUser']],

            AccountPayable::class => [[
                ['label' => 'Descrição', 'value' => fn ($r) => $r->description],
                ['label' => 'Filial', 'value' => fn ($r) => $r->branch?->name],
                ['label' => 'Valor', 'value' => fn ($r) => 'R$ '.number_format((float) $r->amount, 2, ',', '.')],
                ['label' => 'Vencimento', 'value' => fn ($r) => optional($r->due_date)->format('d/m/Y')],
                ['label' => 'Status', 'value' => fn ($r) => ucfirst($r->status)],
            ], ['branch']],

            AccountReceivable::class => [[
                ['label' => 'Descrição', 'value' => fn ($r) => $r->description],
                ['label' => 'Cliente', 'value' => fn ($r) => $r->client?->name],
                ['label' => 'Valor', 'value' => fn ($r) => 'R$ '.number_format((float) $r->amount, 2, ',', '.')],
                ['label' => 'Vencimento', 'value' => fn ($r) => optional($r->due_date)->format('d/m/Y')],
                ['label' => 'Status', 'value' => fn ($r) => ucfirst($r->status)],
            ], ['client']],

            PurchaseOrder::class => [[
                ['label' => 'Fornecedor', 'value' => fn ($r) => $r->supplier?->name],
                ['label' => 'Valor Total', 'value' => fn ($r) => 'R$ '.number_format((float) $r->total_value, 2, ',', '.')],
                ['label' => 'Previsão', 'value' => fn ($r) => optional($r->expected_delivery_date)->format('d/m/Y')],
                ['label' => 'Status', 'value' => fn ($r) => PurchaseOrder::statusOptions()[$r->status] ?? $r->status],
            ], ['supplier']],

            CrmLead::class => [[
                ['label' => 'Lead', 'value' => fn ($r) => $r->name],
                ['label' => 'Empresa', 'value' => fn ($r) => $r->company_name],
                ['label' => 'Estágio', 'value' => fn ($r) => CrmLead::stageLabels()[$r->stage] ?? $r->stage],
                ['label' => 'Vendedor', 'value' => fn ($r) => $r->assignedUser?->name],
                ['label' => 'Valor Estimado', 'value' => fn ($r) => $r->estimated_value ? 'R$ '.number_format((float) $r->estimated_value, 2, ',', '.') : null],
            ], ['assignedUser']],

            default => (function () use ($model) {
                Log::warning("TablePrintController: sem colunas definidas para {$model}, usando fallback genérico.");

                return [[
                    ['label' => 'ID', 'value' => fn ($r) => $r->getKey()],
                    ['label' => 'Criado em', 'value' => fn ($r) => optional($r->created_at)->format('d/m/Y H:i')],
                ], []];
            })(),
        };
    }
}

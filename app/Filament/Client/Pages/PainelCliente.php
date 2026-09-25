<?php

namespace App\Filament\Client\Pages;

use App\Models\AccountReceivable;
use App\Models\Client;
use App\Models\Contract;
use App\Models\MaintenanceOrder;
use Filament\Pages\Page;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;

/**
 * Página inicial do Portal do Cliente -- antes disso o painel abria na
 * Dashboard padrão do Filament, sem nenhum widget registrado
 * (app/Filament/Client/Widgets/ vazio), então era uma tela em branco.
 * Pedido do usuário 2026-09-25: "uma pagina seca, fria e sem nenhuma
 * informaçao, como lista de equipamentos locados, manutencoes no
 * equipamento, um timeline com o tempo da locação" -- inspirado nos
 * mockups do site institucional (erp-cmms/, software-gestao-de-servicos/).
 * Registrada como home do painel via ClientPanelProvider::homeUrl().
 */
class PainelCliente extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Início';

    protected static ?int $navigationSort = -10;

    protected static ?string $slug = 'inicio';

    protected static string $view = 'filament.client.pages.painel-cliente';

    public function getViewData(): array
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        $contracts = Contract::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id)
            ->with('asset')
            ->orderByDesc('start_date')
            ->get();

        $activeContracts = $contracts->where('is_active', true);

        $openOrders = MaintenanceOrder::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id)
            ->whereNotIn('status', ['Concluída', 'Cancelada'])
            ->count();

        $recentOrders = MaintenanceOrder::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id)
            ->with('asset')
            ->latest('created_at')
            ->limit(5)
            ->get();

        $nextInvoice = AccountReceivable::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id)
            ->where('status', '!=', 'pago')
            ->orderBy('due_date')
            ->first();

        $recentInvoices = AccountReceivable::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id)
            ->orderByDesc('due_date')
            ->limit(6)
            ->get();

        $invoicesPagas = $recentInvoices->where('status', 'pago')->count();
        $invoicesAbertas = $recentInvoices->whereIn('status', ['pendente', 'atrasado'])->count();

        // Timeline de vigência de cada contrato ativo com equipamento --
        // percentual decorrido entre start_date e end_date, pra desenhar a
        // barra de progresso (pedido explícito do usuário).
        $equipmentTimelines = $activeContracts
            ->filter(fn (Contract $c) => $c->asset_id !== null)
            ->map(function (Contract $contract) {
                $start = $contract->start_date;
                $end = $contract->end_date;
                $percent = null;
                $daysRemaining = null;

                if ($start && $end && $end->greaterThan($start)) {
                    $totalDays = $start->diffInDays($end);
                    $elapsedDays = min($totalDays, max(0, $start->diffInDays(now())));
                    $percent = $totalDays > 0 ? (int) round(($elapsedDays / $totalDays) * 100) : 0;
                    $daysRemaining = max(0, now()->diffInDays($end, false) > 0 ? now()->diffInDays($end) : 0);
                }

                return [
                    'contract' => $contract,
                    'asset' => $contract->asset,
                    'percent' => $percent,
                    'days_remaining' => $daysRemaining,
                ];
            });

        return [
            'client' => $client,
            'stats' => [
                'equipamentos' => $activeContracts->whereNotNull('asset_id')->count(),
                'contratos_vigentes' => $activeContracts->count(),
                'os_em_andamento' => $openOrders,
                'proxima_fatura' => $nextInvoice,
            ],
            'equipmentTimelines' => $equipmentTimelines,
            'recentOrders' => $recentOrders,
            'recentInvoices' => $recentInvoices,
            'invoicesPagas' => $invoicesPagas,
            'invoicesAbertas' => $invoicesAbertas,
        ];
    }

    private function guard(): Guard
    {
        return Auth::guard('client');
    }
}

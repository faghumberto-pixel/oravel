<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\EquipmentPickupRequest;
use App\Models\MaintenanceOrder;
use App\Models\SolicitacaoLocacao;
use App\Support\Tenancy;

/**
 * Impressão minimalista do histórico de um Client (mensagens +
 * pendências), agregando 3 fontes diferentes -- TablePrintController não
 * serve aqui (espera 1 único model+ids), mesmo motivo que já levou
 * MaintenanceKanban a ter seu próprio MaintenanceKanbanPrintController.
 */
class ClientManagementPrintController extends Controller
{
    public function show(string $client)
    {
        $tenant = Tenancy::current();
        abort_unless($tenant, 403);

        $record = Client::where('tenant_id', $tenant->id)->findOrFail($client);

        $messages = ClientMessage::where('tenant_id', $tenant->id)
            ->where('client_id', $record->id)
            ->orderBy('created_at')
            ->get();

        $solicitacoes = SolicitacaoLocacao::where('tenant_id', $tenant->id)
            ->where('customer_id', $record->id)
            ->whereIn('status_comercial', ['proposta_em_andamento', 'reserva_manutencao'])
            ->get();

        $ordens = MaintenanceOrder::where('tenant_id', $tenant->id)
            ->where('client_id', $record->id)
            ->whereNotIn('status', ['Concluída', 'Completado', 'Cancelada'])
            ->get();

        $retiradas = EquipmentPickupRequest::where('tenant_id', $tenant->id)
            ->where('client_id', $record->id)
            ->where('status', '!=', EquipmentPickupRequest::STATUS_CONCLUIDO)
            ->get();

        return view('reports.client-management-print', [
            'client' => $record,
            'messages' => $messages,
            'solicitacoes' => $solicitacoes,
            'ordens' => $ordens,
            'retiradas' => $retiradas,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);
    }
}

<?php

namespace App\Observers;

use App\Filament\Resources\SolicitacaoLocacaoResource;
use App\Models\Asset;
use App\Models\EquipmentDamage;
use App\Models\EquipmentReplacement;
use App\Models\MaintenanceOrder;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\User;
use App\Notifications\ClientRequestStatusUpdatedNotification;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Notification as LaravelNotification;

class SolicitacaoLocacaoObserver
{
    /**
     * Ver ContractObserver::notifyLocationChanged() / EquipmentReplacementObserver::notifyRole()
     * -- mesmo motivo: nao usar User::where('role', $nome) direto (coluna
     * que nem existe formalmente pra isso, sem tenant scoping) nem
     * User::role($nome) do Spatie puro (resolve globalmente, ignora
     * tenant_id). Ate 2026-07-14 este metodo tambem nunca rodava de
     * verdade: o observer nao estava registrado em AppServiceProvider.
     */
    public function updated(SolicitacaoLocacao $solicitacao): void
    {
        if (! $solicitacao->wasChanged('status_comercial')) {
            return;
        }

        $this->notifyClientStatusChanged($solicitacao);

        if ($solicitacao->status_comercial === 'contrato_fechado') {
            $this->notifyLogisticaContratoFechado($solicitacao);

            return;
        }

        // Saiu de "reserva_manutencao" pra qualquer coisa que NAO seja
        // contrato_fechado (cancelado, ou revertida pra proposta) --
        // revoga a reserva sozinha. contrato_fechado nao entra aqui de
        // proposito: so' chega nesse status com o Ativo ja disponivel
        // (regra em SolicitacaoLocacao::booted()), entao a OS de Reserva
        // ja foi concluida manualmente antes (ver ReservasUrgentes::concluirReserva()).
        if ($solicitacao->getOriginal('status_comercial') === 'reserva_manutencao') {
            $this->revogarReservaAbandonada($solicitacao);
        }
    }

    /**
     * Só notifica o Client nos 2 status finais relevantes pra ele --
     * proposta_em_andamento/reserva_manutencao são internos, não geram
     * e-mail.
     */
    private function notifyClientStatusChanged(SolicitacaoLocacao $solicitacao): void
    {
        if (! in_array($solicitacao->status_comercial, ['contrato_fechado', 'cancelado'], true)) {
            return;
        }

        $client = $solicitacao->customer;
        if (! $client?->portal_access_enabled_at) {
            return;
        }

        $label = $solicitacao->status_comercial === 'contrato_fechado' ? 'Contrato Fechado' : 'Cancelado';

        LaravelNotification::send($client, new ClientRequestStatusUpdatedNotification(
            'Solicitação de Equipamento',
            $label,
            '/cliente/solicitar-equipamento',
        ));
    }

    private function revogarReservaAbandonada(SolicitacaoLocacao $solicitacao): void
    {
        $ordens = MaintenanceOrder::where('solicitacao_locacao_id', $solicitacao->id)
            ->where('maintenance_type', MaintenanceOrder::TYPE_RESERVA)
            ->whereNotIn('status', ['Concluída', 'Cancelada', 'Completado'])
            ->with('asset')
            ->get();

        foreach ($ordens as $ordem) {
            $ordem->update(['status' => 'Cancelada']);

            if ($ordem->asset && $ordem->asset->status === Asset::STATUS_RESERVADO) {
                $ordem->asset->update(['status' => Asset::STATUS_DISPONIVEL]);
            }

            $this->notifyManutencaoReservaRevogada($solicitacao, $ordem);
        }
    }

    private function notifyManutencaoReservaRevogada(SolicitacaoLocacao $solicitacao, MaintenanceOrder $ordem): void
    {
        $role = Role::where('name', EquipmentDamage::ROLE_GERENTE_MANUTENCAO)
            ->where('guard_name', 'web')
            ->where('tenant_id', $solicitacao->tenant_id)
            ->first();

        if (! $role) {
            return;
        }

        $recipients = User::role($role)->where('tenant_id', $solicitacao->tenant_id)->get();

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title('Reserva revogada automaticamente')
                ->body('A Solicitação de '.($solicitacao->customer?->name ?? 'cliente não informado')
                    .' saiu de "Reservar para Manutenção" -- o Ativo '.($ordem->asset?->name ?? '—')
                    .' foi liberado de volta pra disponível.')
                ->actions([
                    Action::make('ver')->button()->url(SolicitacaoLocacaoResource::getUrl('edit', ['record' => $solicitacao])),
                ])
                ->warning()
                ->sendToDatabase($recipient);
        }
    }

    private function notifyLogisticaContratoFechado(SolicitacaoLocacao $solicitacao): void
    {
        $role = Role::where('name', EquipmentReplacement::ROLE_LOGISTICA)
            ->where('guard_name', 'web')
            ->where('tenant_id', $solicitacao->tenant_id)
            ->first();

        if (! $role) {
            return;
        }

        $recipients = User::role($role)
            ->where('tenant_id', $solicitacao->tenant_id)
            ->get();

        $saida = $solicitacao->data_saida_prevista?->format('d/m/Y') ?? 'a combinar';

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title('Novo contrato fechado — despacho a organizar')
                ->body('Ativo/Categoria: '.($solicitacao->asset?->name ?? $solicitacao->category?->name ?? '—')
                    ."\nData de Saída: {$saida}"
                    ."\nCliente: ".($solicitacao->customer?->name ?? '—'))
                ->actions([
                    Action::make('ver')
                        ->button()
                        ->url(SolicitacaoLocacaoResource::getUrl('edit', ['record' => $solicitacao])),
                ])
                ->warning()
                ->sendToDatabase($recipient);
        }
    }
}

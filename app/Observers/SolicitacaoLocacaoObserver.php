<?php

namespace App\Observers;

use App\Filament\Resources\SolicitacaoLocacaoResource;
use App\Models\EquipmentReplacement;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;

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
        if (! $solicitacao->wasChanged('status_comercial') || $solicitacao->status_comercial !== 'contrato_fechado') {
            return;
        }

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

<?php

namespace App\Observers;

use App\Models\SolicitacaoLocacao;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;

class SolicitacaoLocacaoObserver
{
    public function updated(SolicitacaoLocacao $solicitacao): void
    {
        // Verifica se o status mudou para contrato_fechado
        if ($solicitacao->wasChanged('status_comercial') && $solicitacao->status_comercial === 'contrato_fechado') {
            
            // Busca o Supervisor de Pátio ou Mecânico-Chefe (ajuste conforme seu role/sistema)
            $recipients = User::where('tenant_id', $solicitacao->tenant_id)
                ->where('role', 'oficina') // Ajuste o filtro para os usuários da oficina
                ->get();

            foreach ($recipients as $recipient) {
                Notification::make()
                    ->title('🚨 Urgência Comercial: Novo Contrato')
                    ->body("Ativo/Categoria: " . ($solicitacao->asset?->name ?? $solicitacao->category->name) . 
                           "\nData de Saída: " . $solicitacao->data_saida_prevista->format('d/m/Y') . 
                           "\nCliente: " . $solicitacao->customer->name)
                    ->actions([
                        Action::make('ver')
                            ->button()
                            ->url(route('filament.admin.resources.solicitacoes-locacaos.edit', ['tenant' => $solicitacao->tenant_id, 'record' => $solicitacao->id])),
                    ])
                    ->danger()
                    ->sendToDatabase($recipient);
            }
        }
    }
}
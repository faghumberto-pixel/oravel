<?php

namespace App\Observers;

use App\Models\Asset;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;

class AssetObserver
{
    public function updated(Asset $asset): void
    {
        // Verifica se o status mudou para 'manutencao' E é criticidade 5
        if ($asset->isDirty('status') && $asset->status === 'manutencao' && $asset->criticidade_peso === 5) {
            
            // Busca os responsáveis pela Oficina (ajuste o papel conforme seu sistema)
            $recipients = User::role('oficina')->get();

            foreach ($recipients as $recipient) {
                Notification::make()
                    ->title('🚨 ALERTA CRÍTICO: Ativo em Manutenção')
                    ->body("O ativo {$asset->name} ({$asset->patrimonio}) é de ALTA PRIORIDADE e entrou em manutenção.")
                    ->danger()
                    ->persistent() // O alerta não sai da tela até ser lido
                    ->actions([
                        Action::make('view')
                            ->button()
                            ->url(route('filament.admin.resources.assets.edit', $asset->id)),
                    ])
                    ->sendToDatabase($recipient);
            }
        }
    }
}
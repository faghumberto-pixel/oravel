<?php

namespace App\Observers;

use App\Models\Asset;
use App\Models\Role;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;

class AssetObserver
{
    public function updated(Asset $asset): void
    {
        // Verifica se o status mudou para 'manutencao' E é criticidade 5
        if ($asset->isDirty('status') && $asset->status === 'manutencao' && $asset->criticidade_peso === 5) {

            // Nao usar User::role('oficina') direto: Spatie resolve o papel
            // por nome globalmente (Role::findByName), ignorando tenant_id --
            // com varios tenants tendo cada um seu proprio registro "oficina",
            // sempre resolveria pro primeiro criado no banco inteiro e
            // notificaria (ou deixaria de notificar) o tenant errado. Ver
            // EquipmentReplacementObserver::notifyRole() pro mesmo bug.
            $oficinaRole = Role::where('name', 'oficina')
                ->where('guard_name', 'web')
                ->where('tenant_id', $asset->tenant_id)
                ->first();

            $recipients = $oficinaRole
                ? User::role($oficinaRole)->where('tenant_id', $asset->tenant_id)->get()
                : collect();

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

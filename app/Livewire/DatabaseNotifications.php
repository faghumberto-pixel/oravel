<?php

namespace App\Livewire;

use App\Support\CentralNotifications;
use Filament\Facades\Filament;
use Filament\Notifications\Livewire\DatabaseNotifications as BaseDatabaseNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Cada pessoa pode excluir (uma ou todas) as PROPRIAS notificacoes do sino -- pedido do dono
 * (12/10/2026), apos o spam de "Compromisso vencido" na Central. O parent ja escopa a query
 * ao usuario logado, entao removeNotification()/clearNotifications() so apagam as dele.
 * (Antes ficavam como no-op por "auditoria", mas registros:limpar ja apaga notificacoes antigas.)
 * Para NAO receber um tipo de aviso: "Minhas notificacoes" (App\Support\NotificationCategories).
 *
 * Registrado em AppServiceProvider::boot() por cima do
 * Livewire::component('database-notifications', ...) que o proprio
 * pacote ja registra em NotificationsServiceProvider::packageBooted().
 */
class DatabaseNotifications extends BaseDatabaseNotifications
{
    /**
     * Escopo por painel: avisos da operação (pagamento, assinatura, atraso, acesso liberado de
     * clientes) são só da CENTRAL. O super admin também usa o app, e lá o sino não pode mostrar
     * esses avisos (pedido do dono, 02/10/2026). Vale também para avisos antigos, sem a marca:
     * qualquer um com link para /central/ some do app. Na Central aparece tudo.
     */
    public function getNotificationsQuery(): Builder|Relation
    {
        $query = parent::getNotificationsQuery();

        if (Filament::getCurrentPanel()?->getId() === 'central') {
            return $query;
        }

        // O sino do super admin (dono do SaaS, tenant Oravel) é o da CENTRAL: no app -- principalmente
        // quando ele atua como outro tenant -- não aparece nenhum aviso pessoal dele (pedido do
        // usuário 05/10/2026: "o sino do tenant Oravel na Central").
        if (Filament::auth()->user()?->isSuperAdmin()) {
            return $query->whereRaw('1 = 0');
        }

        return CentralNotifications::exclude($query);
    }
}

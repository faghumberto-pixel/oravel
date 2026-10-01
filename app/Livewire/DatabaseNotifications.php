<?php

namespace App\Livewire;

use Filament\Facades\Filament;
use Filament\Notifications\Livewire\DatabaseNotifications as BaseDatabaseNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Attributes\On;

/**
 * Notificacoes servem de comprovacao de auditoria (data/hora, quem
 * recebeu, etc) -- ninguem pode conseguir apagar uma linha da tabela
 * notifications pela UI. O componente original tem 2 pontos de exclusao
 * real (removeNotification()/clearNotifications(), ver
 * vendor/filament/notifications/src/Livewire/DatabaseNotifications.php);
 * ambos viram no-op aqui. "Marcar como lido" nao muda -- so seta
 * read_at, a linha continua existindo.
 *
 * Registrado em AppServiceProvider::boot() por cima do
 * Livewire::component('database-notifications', ...) que o proprio
 * pacote ja registra em NotificationsServiceProvider::packageBooted()
 * (o ultimo registro pro mesmo nome vence).
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

        return $query
            ->whereRaw("coalesce(data::json->'viewData'->>'scope', '') <> 'central'")
            ->whereRaw('data::text not like ?', ['%/central/%'])
            ->whereRaw('data::text not like ?', ['%\\/central\\/%']);
    }

    // PHP nao herda atributos em metodo sobrescrito -- precisa redeclarar
    // #[On(...)] aqui, senao o listener do evento nem fica registrado
    // nessa subclasse.
    #[On('notificationClosed')]
    public function removeNotification(string $id): void
    {
        // Intencionalmente vazio -- nao apaga.
    }

    public function clearNotifications(): void
    {
        // Intencionalmente vazio -- nao apaga.
    }
}

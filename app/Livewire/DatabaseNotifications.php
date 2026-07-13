<?php

namespace App\Livewire;

use Filament\Notifications\Livewire\DatabaseNotifications as BaseDatabaseNotifications;
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

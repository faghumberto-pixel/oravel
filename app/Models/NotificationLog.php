<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Subclass fina de Illuminate\Notifications\DatabaseNotification (tabela
 * `notifications`, ja usada pelo sininho do Filament) so pra poder aplicar
 * HasSaaSMetadata -- vira modulo real no SaaSRegistry (plano/permissao),
 * sem mudar como as notificacoes sao gravadas (continuam indo pra mesma
 * tabela, Notification::sendNow()/sendToDatabase() no fundo usam
 * Illuminate\Notifications\DatabaseNotification independente dessa
 * subclass so de leitura).
 *
 * Sem BelongsToTenant de proposito: essa tabela nao tem coluna tenant_id
 * (notifiable_id aponta pra um User, que tambem nao usa BelongsToTenant --
 * ver CLAUDE.md). O escopo de tenant fica manual em
 * NotificationLogResource::getEloquentQuery(), mesmo padrao de
 * UserActivityLogResource.
 */
class NotificationLog extends DatabaseNotification
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_notification_logs';

    protected static ?string $saasPermissionSlug = 'auditoria_notificacao';

    protected static ?string $saasModuleLabel = 'Auditoria de Notificações';
}

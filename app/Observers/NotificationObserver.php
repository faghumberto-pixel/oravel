<?php

namespace App\Observers;

use Illuminate\Notifications\DatabaseNotification;
use Filament\Facades\Filament;

class NotificationObserver
{
    public function creating(DatabaseNotification $notification): void
    {
        // Pega o tenant que está ativo na sessão do painel agora
        if ($tenant = Filament::getTenant()) {
            // Adiciona o tenant_id dentro do JSON 'data' ou como coluna, 
            // conforme a estrutura que o seu sistema espera
            $data = $notification->data;
            $data['tenant_id'] = $tenant->id;
            $notification->data = $data;
            
            // Se a sua tabela tiver a coluna 'tenant_id' física:
            $notification->tenant_id = $tenant->id;
        }
    }
}
<?php

namespace App\Observers;

use App\Models\ContaPagar;
use App\Models\User;
use App\Notifications\ContaPagarNotification;
use Illuminate\Support\Facades\Notification;

class ContaPagarObserver
{
    public function created(ContaPagar $contaPagar): void
    {
        // Busca os usuários que devem receber (ex: administradores ou setor financeiro)
        $usuariosFinanceiro = User::where('perfil', 'financeiro')->get();

        // Envia a notificação para todos eles de uma vez
        Notification::send($usuariosFinanceiro, new ContaPagarNotification($contaPagar, 'lancamento'));
    }
}
<?php

namespace App\Observers;

use App\Models\AccountPayable;
use App\Models\User;
use App\Notifications\ContaPagarNotification;
use Illuminate\Support\Facades\Notification;

class ContaPagarObserver
{
    public function created(AccountPayable $accountPayable): void
    {
        $usuariosFinanceiro = User::financialNotificationRecipients($accountPayable->tenant_id);

        Notification::send($usuariosFinanceiro, new ContaPagarNotification($accountPayable, 'lancamento'));
    }
}

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
        $usuariosFinanceiro = User::where('tenant_id', $accountPayable->tenant_id)
            ->get()
            ->filter(fn (User $user) => $user->podeReceberFinancas());

        Notification::send($usuariosFinanceiro, new ContaPagarNotification($accountPayable, 'lancamento'));
    }
}

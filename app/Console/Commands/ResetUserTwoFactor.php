<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetUserTwoFactor extends Command
{
    protected $signature = 'user:reset-2fa {email : E-mail do usuário} {--force : Não pedir confirmação}';

    protected $description = 'Remove o 2FA de um usuário que perdeu o acesso ao autenticador (ele reconfigura no próximo login)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        $user = User::withoutGlobalScopes()->whereRaw('lower(email) = ?', [$email])->first();

        if (! $user) {
            $this->error('Usuário não encontrado.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Remover o 2FA de {$user->email}?")) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        $user->disableTwoFactorAuthentication();

        // Nunca registrar segredo nem codigos de recuperacao.
        Log::warning('2FA resetado por comando artisan', [
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'executed_by_os_user' => get_current_user(),
            'executed_at' => now()->toIso8601String(),
        ]);

        $this->info("2FA removido de {$user->email}.");

        return self::SUCCESS;
    }
}

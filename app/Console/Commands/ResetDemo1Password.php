<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetDemo1Password extends Command
{
    protected $signature = 'auth:reset-demo1-password {--password=demo12345 : A nova senha (padrão: demo12345)}';

    protected $description = 'Reseta a senha do usuário demo1 (admin@demo1.com.br) para demo12345 (padrão) ou outra senha fornecida';

    public function handle()
    {
        $this->line('');
        $this->info('=== RESET DE SENHA DEMO1 ===');
        $this->line('');

        $email = 'admin@demo1.com.br';
        $newPassword = $this->option('password');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("✗ Usuário {$email} não encontrado");
            return 1;
        }

        $this->line("Usuário encontrado: {$user->email}");
        $this->line("Tenant: {$user->tenant->name}");
        $this->line("is_approved: " . ($user->is_approved ? 'TRUE' : 'FALSE'));
        $this->line('');

        // Confirmar antes de resetar
        if (!$this->confirm("Tem certeza que quer resetar a senha para '{$newPassword}'?")) {
            $this->info('Cancelado.');
            return 1;
        }

        // Atualizar senha
        DB::table('users')
            ->where('id', $user->id)
            ->update(['password' => Hash::make($newPassword)]);

        // Recarregar
        $user = User::where('email', $email)->first();

        // Verificar
        if (Hash::check($newPassword, $user->password)) {
            $this->info("✓ Senha resetada com sucesso!");
            $this->line("Email: {$user->email}");
            $this->line("Senha: {$newPassword}");
            $this->line('');
            $this->warn('⚠ Teste o login em http://app.oravel.com.br/admin/login');
            return 0;
        } else {
            $this->error("✗ Erro ao resetar senha");
            return 1;
        }
    }
}

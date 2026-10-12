<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GerarChavesPush extends Command
{
    protected $signature = 'colaborador:gerar-chaves-push';

    protected $description = 'Gera as chaves VAPID do push do app do colaborador e grava no .env (não mostra a chave privada)';

    public function handle(): int
    {
        $env = base_path('.env');
        $texto = file_get_contents($env);

        if (preg_match('/^VAPID_PRIVATE_KEY=\S+/m', $texto)) {
            $this->warn('Já existe VAPID_PRIVATE_KEY no .env. Nada foi alterado.');

            return self::SUCCESS;
        }

        $chaves = VAPID::createVapidKeys();
        $linhas = ['VAPID_PUBLIC_KEY' => $chaves['publicKey'], 'VAPID_PRIVATE_KEY' => $chaves['privateKey'], 'VAPID_SUBJECT' => 'mailto:contato@oravel.com.br'];

        foreach ($linhas as $chave => $valor) {
            $texto = preg_match("/^{$chave}=.*$/m", $texto)
                ? preg_replace("/^{$chave}=.*$/m", "{$chave}={$valor}", $texto, 1)
                : rtrim($texto, "\n")."\n{$chave}={$valor}\n";
        }

        file_put_contents($env, $texto);
        $this->info('Chaves gravadas no .env. Chave pública: '.$chaves['publicKey']);

        return self::SUCCESS;
    }
}

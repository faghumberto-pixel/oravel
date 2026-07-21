<?php

namespace App\Console\Commands;

use App\Mail\GenericPdfMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Verifica a infraestrutura de e-mail (config/mail.php + credenciais SMTP)
 * mandando um e-mail de teste de verdade -- sem isso, um erro de SMTP
 * (host/porta/senha errada) so' apareceria na hora que um fluxo real
 * (orçamento, laudo) tentasse mandar e-mail pela primeira vez.
 */
class TestMailSending extends Command
{
    protected $signature = 'mail:test {destinatario : E-mail que vai receber o teste}';

    protected $description = 'Manda um e-mail de teste real pra verificar a configuração SMTP';

    public function handle(): int
    {
        $destinatario = $this->argument('destinatario');

        $this->info('Mailer configurado: '.config('mail.default'));
        $this->info('Host: '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
        $this->info('Remetente: '.config('mail.from.address'));
        $this->info("Enviando e-mail de teste pra {$destinatario}...");

        try {
            Mail::to($destinatario)->send(new GenericPdfMail(
                subjectLine: 'Teste de envio -- Oravel',
                greeting: 'Olá!',
                bodyText: 'Este é um e-mail de teste da infraestrutura de envio do sistema Oravel. Se você recebeu isso, a configuração SMTP está funcionando corretamente.',
            ));
        } catch (\Throwable $e) {
            $this->error('Falha ao enviar: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('E-mail enviado com sucesso (sem erro do servidor SMTP).');

        return self::SUCCESS;
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Envia a planilha de manutenção preventiva (isca da landing /planilha-manutencao-preventiva).
 * O arquivo fica em storage/app/private e só sai como anexo: nunca há link público para ele.
 */
class PlanilhaManutencaoMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public const ARQUIVO = 'Oravel_Controle_Manutencao_Preventiva.xlsx';

    public function __construct(public string $nome) {}

    public static function caminho(): string
    {
        return storage_path('app/private/'.self::ARQUIVO);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Sua planilha de manutenção preventiva (Oravel)');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.planilha-manutencao');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromPath(self::caminho())
                ->as(self::ARQUIVO)
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}

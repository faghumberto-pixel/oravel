<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail generico com corpo de texto + anexo PDF opcional -- base
 * reutilizavel pra qualquer fluxo que precise mandar um PDF por e-mail
 * (laudo de avaria, orçamento, dossiê), sem precisar de uma classe Mailable
 * nova pra cada caso. Especializar (ex: App\Mail\QuoteMail) so' faz sentido
 * quando o fluxo especifico precisar de logica alem de "corpo + anexo".
 *
 * SMTP compartilhado (uma conta so', contato@oravel.com.br) pra todo
 * tenant -- decisao explicita do usuario, sem SMTP individual por locadora
 * por enquanto. $senderDisplayName/$replyToAddress emulam o envio "em nome
 * do tenant": remetente mostra o nome da locadora, resposta cai na caixa
 * dela, mas a autenticacao SMTP de verdade continua sendo so' a da Oravel
 * (evita problema de SPF/DKIM de mandar "como se fosse" outro dominio).
 */
class GenericPdfMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array{content: string, filename: string, mime?: ?string}>  $extraAttachments
     *         Anexos genericos adicionais (alem do pdfContent unico abaixo) --
     *         cada item ja vem com o conteudo em memoria, mesmo esquema do
     *         pdfContent. Usado pela Caixa de E-mail, que pode mandar
     *         varios arquivos/imagens de uma vez. Nome diferente de
     *         "attachments" de proposito -- Illuminate\Mail\Mailable ja
     *         declara uma propriedade protegida "$attachments" internamente
     *         (acumulada durante o build), reusar o nome quebra a classe.
     */
    public function __construct(
        public string $subjectLine,
        public string $greeting,
        public string $bodyText,
        public ?string $pdfContent = null,
        public ?string $pdfFilename = null,
        public ?string $senderDisplayName = null,
        public ?string $replyToAddress = null,
        public array $extraAttachments = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            from: $this->senderDisplayName
                ? new Address(config('mail.from.address'), $this->senderDisplayName.' via Oravel')
                : null,
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic-pdf',
            with: [
                'greeting' => $this->greeting,
                'bodyText' => $this->bodyText,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        if ($this->pdfContent) {
            $attachments[] = Attachment::fromData(fn () => $this->pdfContent, $this->pdfFilename ?? 'documento.pdf')
                ->withMime('application/pdf');
        }

        foreach ($this->extraAttachments as $attachment) {
            $item = Attachment::fromData(fn () => $attachment['content'], $attachment['filename']);

            if (! empty($attachment['mime'])) {
                $item = $item->withMime($attachment['mime']);
            }

            $attachments[] = $item;
        }

        return $attachments;
    }
}

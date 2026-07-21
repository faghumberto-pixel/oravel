<?php

namespace Tests\Feature;

use App\Mail\GenericPdfMail;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GenericPdfMailTest extends TestCase
{
    public function test_mail_has_correct_subject_and_body(): void
    {
        Mail::fake();

        Mail::to('cliente@exemplo.com.br')->send(new GenericPdfMail(
            subjectLine: 'Orçamento #123',
            greeting: 'Olá, João!',
            bodyText: 'Segue o orçamento solicitado.',
        ));

        Mail::assertSent(GenericPdfMail::class, function (GenericPdfMail $mail) {
            return $mail->hasTo('cliente@exemplo.com.br')
                && $mail->subjectLine === 'Orçamento #123'
                && $mail->greeting === 'Olá, João!';
        });
    }

    public function test_mail_attaches_pdf_when_provided(): void
    {
        Mail::fake();

        $fakePdfContent = '%PDF-1.4 conteudo fake de teste';

        Mail::to('cliente@exemplo.com.br')->send(new GenericPdfMail(
            subjectLine: 'Laudo Técnico',
            greeting: 'Olá!',
            bodyText: 'Segue o laudo em anexo.',
            pdfContent: $fakePdfContent,
            pdfFilename: 'laudo-123.pdf',
        ));

        Mail::assertSent(GenericPdfMail::class, function (GenericPdfMail $mail) {
            return $mail->hasAttachment(
                Attachment::fromData(fn () => '%PDF-1.4 conteudo fake de teste', 'laudo-123.pdf')
                    ->withMime('application/pdf')
            );
        });
    }

    public function test_mail_has_no_attachment_when_pdf_not_provided(): void
    {
        $mail = new GenericPdfMail(
            subjectLine: 'Sem anexo',
            greeting: 'Olá!',
            bodyText: 'Mensagem simples.',
        );

        $this->assertCount(0, $mail->attachments());
    }
}

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

    public function test_mail_attaches_generic_files_alongside_pdf(): void
    {
        Mail::fake();

        Mail::to('cliente@exemplo.com.br')->send(new GenericPdfMail(
            subjectLine: 'Com anexos genéricos',
            greeting: 'Olá!',
            bodyText: 'Segue.',
            pdfContent: '%PDF-1.4 conteudo fake',
            pdfFilename: 'laudo.pdf',
            extraAttachments: [
                ['content' => 'conteudo do arquivo 1', 'filename' => 'foto1.jpg', 'mime' => 'image/jpeg'],
                ['content' => 'conteudo do arquivo 2', 'filename' => 'planilha.xlsx', 'mime' => null],
            ],
        ));

        Mail::assertSent(GenericPdfMail::class, function (GenericPdfMail $mail) {
            return $mail->hasAttachment(
                Attachment::fromData(fn () => '%PDF-1.4 conteudo fake', 'laudo.pdf')->withMime('application/pdf')
            ) && $mail->hasAttachment(
                Attachment::fromData(fn () => 'conteudo do arquivo 1', 'foto1.jpg')->withMime('image/jpeg')
            ) && $mail->hasAttachment(
                Attachment::fromData(fn () => 'conteudo do arquivo 2', 'planilha.xlsx')
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

    public function test_mail_shows_tenant_name_and_replies_to_tenant_when_provided(): void
    {
        // SMTP compartilhado (uma conta so') -- o "envio em nome do tenant"
        // e' so' nome de exibicao + reply-to, a conta autenticada de
        // verdade continua sendo config('mail.from.address').
        $mail = new GenericPdfMail(
            subjectLine: 'Orçamento #123',
            greeting: 'Olá!',
            bodyText: 'Segue o orçamento.',
            senderDisplayName: 'CampGeradores',
            replyToAddress: 'contato@campgeradores.com.br',
        );

        $envelope = $mail->envelope();

        $this->assertSame(config('mail.from.address'), $envelope->from->address);
        $this->assertSame('CampGeradores via Oravel', $envelope->from->name);
        $this->assertSame('contato@campgeradores.com.br', $envelope->replyTo[0]->address);
    }

    public function test_mail_uses_default_sender_when_tenant_name_not_provided(): void
    {
        $mail = new GenericPdfMail(
            subjectLine: 'Aviso do sistema',
            greeting: 'Olá!',
            bodyText: 'Mensagem interna.',
        );

        $envelope = $mail->envelope();

        $this->assertNull($envelope->from);
        $this->assertCount(0, $envelope->replyTo);
    }
}

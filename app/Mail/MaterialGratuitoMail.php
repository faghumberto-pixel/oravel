<?php

namespace App\Mail;

use App\Support\Iscas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de qualquer material gratuito (isca) do site: assunto, texto e arquivo vêm de Iscas::materiais().
 * O arquivo fica em storage/app/private e só sai como anexo: nunca há link público para ele.
 */
class MaterialGratuitoMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public string $origem, public string $nome) {}

    /** @return array{assunto: string, material: string, miolo: string, arquivo: string, mime: string} */
    public function material(): array
    {
        return Iscas::materiais()[$this->origem] ?? throw new \InvalidArgumentException("Isca desconhecida: {$this->origem}");
    }

    public static function caminho(string $origem): string
    {
        return storage_path('app/private/'.(Iscas::materiais()[$origem]['arquivo'] ?? ''));
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->material()['assunto']);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.material-gratuito', with: ['nome' => $this->nome, 'material' => $this->material()]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $m = $this->material();

        return [Attachment::fromPath(self::caminho($this->origem))->as($m['arquivo'])->withMime($m['mime'])];
    }
}

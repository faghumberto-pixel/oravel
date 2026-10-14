<?php

namespace App\Mail;

use App\Models\TreinamentoResultado;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso à Oravel de cada envio do teste público de treinamento.
 */
class TreinamentoResultadoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array{n: int, pergunta: string, escolhida: ?string, certa: bool}>  $linhas
     */
    public function __construct(
        public TreinamentoResultado $resultado,
        public array $linhas,
        public string $titulo,
    ) {}

    public function envelope(): Envelope
    {
        $nota = number_format((float) $this->resultado->nota, 1, ',', '');

        return new Envelope(
            subject: "Teste Oravel: {$this->resultado->nome} ({$this->resultado->funcao}) nota {$nota}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.treinamento-resultado',
            with: [
                'resultado' => $this->resultado,
                'linhas' => $this->linhas,
                'titulo' => $this->titulo,
                'quando' => $this->resultado->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            ],
        );
    }
}

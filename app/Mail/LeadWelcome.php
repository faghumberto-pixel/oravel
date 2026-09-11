<?php

namespace App\Mail;

use App\Models\LandingPageLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeadWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LandingPageLead $lead)
    {}

    public function envelope(): Envelope
    {
        $product = $this->lead->product === 'wms' ? 'WMS' : 'CRM';
        return new Envelope(
            subject: "Bem-vindo ao Teste Grátis Oravel {$product}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.lead-welcome',
        );
    }
}

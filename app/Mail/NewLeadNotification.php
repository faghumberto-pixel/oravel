<?php

namespace App\Mail;

use App\Models\CrmLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewLeadNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CrmLead $lead)
    {}

    public function envelope(): Envelope
    {
        $product = str_contains($this->lead->source, 'wms') ? 'WMS' : 'CRM';
        return new Envelope(
            subject: "Novo Lead - Oravel {$product}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.new-lead-notification',
        );
    }
}

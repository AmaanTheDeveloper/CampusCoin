<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PublicContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{name: string, email: string, subject: string, message: string}  $payload
     */
    public function __construct(public array $payload) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->payload['email'], $this->payload['name'])],
            subject: '[Campus Coin Contact] '.$this->payload['subject'],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.public-contact-message',
        );
    }
}

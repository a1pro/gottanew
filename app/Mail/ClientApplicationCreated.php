<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientApplicationCreated extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;

   public function __construct(string $name)
    {
       $this->name = $name;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Gotta - Your Account Has Been Created',
        );
    }


    public function content(): Content
    {
        return new Content(
            view: 'emails.client-application-created',
            with: [
                'name' => $this->name,
            ],
        );
    }
}
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $email;
    public string $token;
    public string $url;

    public function __construct(string $email, string $token)
    {
        $this->email = $email;
        $this->token = $token;

        $this->url = config('app.frontend_url')
            . "/reset-password?token={$token}&email={$email}";
    }

    public function envelope(): Envelope
    {
        return new Envelope(
           subject: 'Reset Your Password'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reset-password'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
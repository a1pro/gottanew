<?php
// app/Mail/CoachApplicationReceived.php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CoachApplicationReceived extends Mailable
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
            subject: 'Your Coach Application Has Been Received',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.coach-application-received',
            with: [
                'name' => $this->name,
            ],
        );
    }
}
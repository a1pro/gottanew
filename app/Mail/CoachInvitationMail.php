<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CoachInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $email;
    public $token;
    public $url;

    /**
     * Create a new message instance.
     */
    public function __construct($email, $token)
    {
        $this->email = $email;
        $this->token = $token;

        // $this->url = config('app.frontend_url') .
        //     "/coach-set-password?token={$token}&email={$email}";

        $this->url = config('app.frontend_url') .
    "/coach-onboard?token={$token}";
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Coach Account Has Been Approved'
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.coach_invitation'
        );
    }

    /**
     * Attachments
     */
    public function attachments(): array
    {
        return [];
    }
}
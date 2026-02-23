<?php
// app/Mail/CoachApplicationApproved.php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CoachApplicationApproved extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Congratulations! Your Coach Application Has Been Approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.coach-application-approved',
            with: [
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
        );
    }
}
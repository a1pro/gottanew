<?php
// app/Mail/CoachApplicationRejected.php

namespace App\Mail;

use App\Models\User;
use App\Models\CoachApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CoachApplicationRejected extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public CoachApplication $application;

    public function __construct(User $user, CoachApplication $application)
    {
        $this->user = $user;
        $this->application = $application;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Update on Your Coach Application',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.coach-application-rejected',
            with: [
                'name' => $this->user->name,
                'reason' => $this->application->admin_notes ?? 'Not specified',
            ],
        );
    }
}
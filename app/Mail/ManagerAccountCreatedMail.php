<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManagerAccountCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $manager, public string $temporaryPassword) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your ScanTrack Manager Account');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.manager-account-created');
    }
}
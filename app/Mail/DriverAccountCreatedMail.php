<?php

namespace App\Mail;

use App\Models\Driver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DriverAccountCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Driver $driver,
        public string $email,
        public string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Tap Track Driver Account');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.driver-account-created');
    }
}
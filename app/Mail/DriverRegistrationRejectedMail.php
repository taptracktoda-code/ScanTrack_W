<?php

namespace App\Mail;

use App\Models\Driver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DriverRegistrationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Driver $driver, public string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ScanTrack Driver Registration Update');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.driver-registration-rejected');
    }
}
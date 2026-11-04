<?php

namespace Tests\Feature;

use App\Mail\Transport\GmailApiTransport;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GmailMailTransportRegistrationTest extends TestCase
{
    public function test_gmail_mailer_uses_the_custom_api_transport(): void
    {
        config([
            'services.gmail.client_id' => 'client-id',
            'services.gmail.client_secret' => 'client-secret',
            'services.gmail.refresh_token' => 'refresh-token',
        ]);
        Mail::purge('gmail');

        $this->assertInstanceOf(GmailApiTransport::class, Mail::mailer('gmail')->getSymfonyTransport());
    }

    public function test_gmail_mailer_requires_oauth_credentials(): void
    {
        config([
            'services.gmail.client_id' => null,
            'services.gmail.client_secret' => null,
            'services.gmail.refresh_token' => null,
        ]);
        Mail::purge('gmail');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Gmail API OAuth credentials must be configured.');

        Mail::mailer('gmail')->getSymfonyTransport();
    }
}

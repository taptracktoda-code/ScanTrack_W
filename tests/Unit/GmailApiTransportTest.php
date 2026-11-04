<?php

namespace Tests\Unit;

use App\Mail\Transport\GmailApiTransport;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;

class GmailApiTransportTest extends TestCase
{
    public function test_it_refreshes_the_oauth_token_and_sends_a_mime_message_through_gmail_api(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], '{"access_token":"access-token"}'),
            new Response(200, [], '{"id":"message-id"}'),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $transport = new GmailApiTransport(
            new Client(['handler' => $stack]),
            'client-id',
            'client-secret',
            'refresh-token',
        );
        $email = (new Email())
            ->from('TapTrack <sender@gmail.com>')
            ->to('recipient@gmail.com')
            ->subject('Test message')
            ->text('This is a test.');

        $sentMessage = $transport->send($email);

        $this->assertNotNull($sentMessage);
        $this->assertCount(2, $history);
        $this->assertSame('https://oauth2.googleapis.com/token', (string) $history[0]['request']->getUri());
        parse_str((string) $history[0]['request']->getBody(), $tokenRequest);
        $this->assertSame('client-id', $tokenRequest['client_id']);
        $this->assertSame('client-secret', $tokenRequest['client_secret']);
        $this->assertSame('refresh-token', $tokenRequest['refresh_token']);
        $this->assertSame('Bearer access-token', $history[1]['request']->getHeaderLine('Authorization'));

        $sendRequest = json_decode((string) $history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $rawMessage = base64_decode(strtr($sendRequest['raw'], '-_', '+/'));
        $this->assertStringContainsString('To: recipient@gmail.com', $rawMessage);
        $this->assertStringContainsString('Subject: Test message', $rawMessage);
        $this->assertStringContainsString('This is a test.', $rawMessage);
    }

    public function test_it_fails_if_google_does_not_return_an_access_token(): void
    {
        $mock = new MockHandler([new Response(200, [], '{"error":"invalid_grant"}')]);
        $transport = new GmailApiTransport(
            new Client(['handler' => HandlerStack::create($mock)]),
            'client-id',
            'client-secret',
            'refresh-token',
        );

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Google did not return a Gmail API access token.');

        $transport->send(
            (new Email())
                ->from('sender@gmail.com')
                ->to('recipient@gmail.com')
                ->subject('Test message')
                ->text('This is a test.')
        );
    }
}

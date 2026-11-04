<?php

namespace App\Mail\Transport;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use JsonException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

final class GmailApiTransport extends AbstractTransport
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SEND_URL = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        try {
            $tokenResponse = $this->http->request('POST', self::TOKEN_URL, [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $this->refreshToken,
                    'grant_type' => 'refresh_token',
                ],
                'timeout' => 15,
            ]);
            $token = json_decode($tokenResponse->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);
        } catch (GuzzleException $exception) {
            $status = $exception instanceof RequestException ? $exception->getResponse()?->getStatusCode() : null;
            throw new TransportException($status
                ? "Gmail API token request failed with HTTP {$status}."
                : 'Unable to reach the Gmail API token endpoint.');
        } catch (JsonException) {
            throw new TransportException('Google returned an invalid OAuth token response.');
        }

        $accessToken = is_array($token) ? ($token['access_token'] ?? null) : null;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new TransportException('Google did not return a Gmail API access token.');
        }

        $rawMessage = rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '=');

        try {
            $this->http->request('POST', self::SEND_URL, [
                'headers' => ['Authorization' => 'Bearer '.$accessToken],
                'json' => ['raw' => $rawMessage],
                'timeout' => 15,
            ]);
        } catch (GuzzleException $exception) {
            $status = $exception instanceof RequestException ? $exception->getResponse()?->getStatusCode() : null;
            throw new TransportException($status
                ? "Gmail API email request failed with HTTP {$status}."
                : 'Unable to reach the Gmail API email endpoint.');
        }
    }

    public function __toString(): string
    {
        return 'gmail-api';
    }
}

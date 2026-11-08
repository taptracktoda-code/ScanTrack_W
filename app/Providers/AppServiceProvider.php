<?php

namespace App\Providers;

use App\Mail\Transport\GmailApiTransport;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        \App\Models\OrganizationQueueEntry::observe(\App\Observers\QueueEntryObserver::class);
        \App\Models\Trip::observe(\App\Observers\OrganizationSnapshotObserver::class);
        \App\Models\DriverLog::observe(\App\Observers\OrganizationSnapshotObserver::class);

        Mail::extend('gmail', function (array $config = []): GmailApiTransport {
            $credentials = config('services.gmail');
            foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
                if (! is_string($credentials[$key] ?? null) || trim($credentials[$key]) === '') {
                    throw new InvalidArgumentException('Gmail API OAuth credentials must be configured.');
                }
            }

            return new GmailApiTransport(
                new Client(),
                $credentials['client_id'],
                $credentials['client_secret'],
                $credentials['refresh_token'],
            );
        });
    }
}

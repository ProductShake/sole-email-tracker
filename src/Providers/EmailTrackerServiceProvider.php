<?php

namespace Productshake\EmailTracker\Providers;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Productshake\EmailTracker\Listeners\TrackEmail;

class EmailTrackerServiceProvider extends ServiceProvider
{
    public function register() {}

    public function boot()
    {
        Event::listen(MessageSent::class, TrackEmail::class);

        // Publish the configuration file for the package
        $this->publishes([
            __DIR__.'/../../config/sole-email-tracker.php' => config_path('sole-email-tracker.php'),
        ], 'email-tracker-config');
    }
}

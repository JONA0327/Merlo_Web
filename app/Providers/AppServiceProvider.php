<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The Openpay PHP SDK has a loading-order bug (its child
        // classes reference their base classes without a namespace
        // prefix, so the SDK dies if you require it the way its
        // own README says). Our local loader (packages/openpay/
        // openpay-php-loader.php) requires the base classes FIRST
        // and then the patched main file. We require it here at
        // boot so any service that uses the Openpay facade has it
        // available without each call paying the require cost.
        require_once base_path('packages/openpay/openpay-php-loader.php');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Registered::class, SendEmailVerificationNotification::class);
    }
}

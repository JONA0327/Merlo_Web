<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        // Long written date for tickets ("1 de octubre del 2026") —
        // forced to the 'es' locale regardless of APP_LOCALE, so ticket
        // dates never silently fall back to English month names if the
        // app locale setting drifts.
        Carbon::macro('toSpanishLongDate', function () {
            /** @var Carbon $this */
            $month = Str::ucfirst($this->copy()->locale('es')->translatedFormat('F'));

            return "{$this->day} de {$month} del {$this->year}";
        });
    }
}

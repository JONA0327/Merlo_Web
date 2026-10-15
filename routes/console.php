<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('seat-holds:prune')->everyFiveMinutes();
// Liberación automática de asientos por transferencia sin pago: desactivada por el momento.
// Schedule::command('transfer-reservations:prune')->hourly();
Schedule::command('return-changes:void-expired')->daily();
Schedule::command('trips:activate-recurring')->dailyAt('00:05');
Schedule::command('guides:provision-recurring')->daily();
Schedule::call(fn () => \App\Models\LandingRoute::pruneExpired())->dailyAt('00:10')->name('trips:prune-expired');
// Envío automático de avisos un día antes: desactivado a petición — el cliente
// solo recibe su aviso en el momento de reservar (comando disponible a mano).
// Schedule::command('reservations:send-day-before-reminders')->dailyAt('09:00');

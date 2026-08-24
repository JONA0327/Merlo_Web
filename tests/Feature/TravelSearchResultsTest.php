<?php

use App\Models\LandingRoute;

test('travel search results show trip details and seat selection', function () {
    LandingRoute::create([
        'from' => 'San Luis Potosí',
        'to' => 'Ciudad de México',
        'duration' => '6h 30m',
        'departure_time' => '06:30',
        'available_seats' => 18,
        'ticket_price' => '$650',
        'price' => '$650',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->get(route('travel.search', ['from' => 'San Luis Potosí', 'to' => 'Ciudad de México']))
        ->assertOk()
        ->assertSee('San Luis Potosí')
        ->assertSee('Ciudad de México')
        ->assertSee('06:30')
        ->assertSee('Asientos disponibles')
        ->assertSee('$650');
});

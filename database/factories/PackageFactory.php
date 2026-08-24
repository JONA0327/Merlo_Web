<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tracking_code' => Package::generateTrackingCode(),
            'status' => Package::STATUS_SIN_ASIGNAR,
        ];
    }

    /**
     * A package that's already been scanned and assigned to a client.
     */
    public function collected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Package::STATUS_RECOLECTADO,
            'client_name' => fake()->name(),
            'client_email' => fake()->safeEmail(),
            'price' => fake()->randomFloat(2, 50, 800),
            'collected_by' => User::factory(),
            'collected_at' => now(),
        ]);
    }
}

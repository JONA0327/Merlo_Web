<?php

namespace Database\Factories;

use App\Models\PackageGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageGroup>
 */
class PackageGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_name' => fake()->name(),
            'client_email' => fake()->safeEmail(),
            'total_price' => fake()->randomFloat(2, 100, 1500),
            'tracking_code' => PackageGroup::generateTrackingCode(),
        ];
    }
}

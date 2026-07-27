<?php

namespace Database\Factories;

use App\Models\Donation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Donation>
 */
class DonationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'donor_name' => fake()->name(),
            'amount_idr' => fake()->numberBetween(10000, 150000),
            'platform' => fake()->randomElement(['manual', 'saweria', 'trakteer']),
            'provider_event_id' => null,
            'message' => fake()->optional()->sentence(),
            'anonymous' => false,
            'public_visible' => true,
            'donated_at' => now(),
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn (): array => [
            'donor_name' => null,
            'anonymous' => true,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => [
            'public_visible' => false,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
class MonitorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'name'             => fake()->optional()->words(2, true),
            'url'              => fake()->url(),
            'method'           => fake()->randomElement(['GET', 'HEAD']),
            'interval_minutes' => fake()->randomElement([1, 2, 3, 5]),
            'current_status'   => fake()->randomElement(['unknown', 'up', 'down']),
            'is_paused'              => false,
            'last_checked_at'        => null,
            'confirmation_threshold'     => 1,
            'consecutive_failures'       => 0,
            'response_time_threshold_ms' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function withInterval(int $minutes): static
    {
        return $this->state(['interval_minutes' => $minutes]);
    }
}

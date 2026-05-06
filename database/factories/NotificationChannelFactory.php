<?php

namespace Database\Factories;

use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationChannel>
 */
class NotificationChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'   => User::factory(),
            'type'      => 'webhook',
            'label'     => fake()->words(2, true),
            'config'    => [
                'url'             => fake()->url(),
                'secret'          => null,
                'timeout_seconds' => 10,
            ],
            'is_active' => true,
        ];
    }

    public function webhook(): static
    {
        return $this->state([
            'type'   => 'webhook',
            'config' => [
                'url'             => fake()->url(),
                'secret'          => null,
                'timeout_seconds' => 10,
            ],
        ]);
    }

    public function slack(): static
    {
        return $this->state([
            'type'   => 'slack',
            'config' => [
                'webhook_url'     => 'https://hooks.slack.com/services/' . fake()->bothify('???/???/???'),
                'timeout_seconds' => 10,
            ],
        ]);
    }

    public function email(): static
    {
        return $this->state([
            'type'   => 'email',
            'config' => ['address' => fake()->email()],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}

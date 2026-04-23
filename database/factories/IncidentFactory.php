<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    public function definition(): array
    {
        $startedAt  = fake()->dateTimeBetween('-30 days', '-1 hour');
        $resolvedAt = fake()->dateTimeBetween($startedAt, 'now');
        $duration   = $resolvedAt->getTimestamp() - $startedAt->getTimestamp();

        return [
            'monitor_id'       => Monitor::factory(),
            'started_at'       => $startedAt,
            'resolved_at'      => $resolvedAt,
            'duration_seconds' => $duration,
        ];
    }

    public function ongoing(): static
    {
        return $this->state([
            'resolved_at'      => null,
            'duration_seconds' => null,
        ]);
    }
}

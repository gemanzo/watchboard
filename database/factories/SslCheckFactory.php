<?php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\SslCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SslCheck>
 */
class SslCheckFactory extends Factory
{
    public function definition(): array
    {
        $validTo = now()->addDays(60);

        return [
            'monitor_id'        => Monitor::factory(),
            'issuer'            => 'Let\'s Encrypt',
            'valid_from'        => now()->subDays(30),
            'valid_to'          => $validTo,
            'days_until_expiry' => 60,
            'is_valid'          => true,
            'error'             => null,
            'checked_at'        => now(),
        ];
    }

    public function expiring(int $daysLeft = 10): static
    {
        return $this->state([
            'valid_to'          => now()->addDays($daysLeft),
            'days_until_expiry' => $daysLeft,
            'is_valid'          => true,
            'error'             => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'valid_to'          => now()->subDays(5),
            'days_until_expiry' => -5,
            'is_valid'          => false,
            'error'             => null,
        ]);
    }

    public function failed(string $error = 'Connection failed'): static
    {
        return $this->state([
            'issuer'            => null,
            'valid_from'        => null,
            'valid_to'          => null,
            'days_until_expiry' => null,
            'is_valid'          => false,
            'error'             => $error,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\StatusPage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StatusPage> */
class StatusPageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'title'       => fake()->words(3, true),
            'slug'        => fake()->unique()->slug(3),
            'description' => fake()->optional()->sentence(),
            'is_active'   => true,
        ];
    }
}

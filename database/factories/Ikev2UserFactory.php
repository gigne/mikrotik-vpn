<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ikev2User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ikev2User>
 */
class Ikev2UserFactory extends Factory
{
    protected $model = Ikev2User::class;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'password' => fake()->password(),
            'assigned_ip' => '192.168.43.' . fake()->unique()->numberBetween(200, 250),
            'notes' => null,
            'expired_at' => null,
            'is_enabled' => true,
        ];
    }
}

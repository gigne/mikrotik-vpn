<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\VpnUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VpnUser>
 */
class VpnUserFactory extends Factory
{
    protected $model = VpnUser::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'username' => 'user-' . fake()->unique()->lexify('??????'),
            'password' => fake()->password(12, 20),
            'assigned_ip' => '192.168.43.' . fake()->numberBetween(100, 200),
            'notes' => fake()->optional()->sentence(),
            'is_enabled' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $a) => [
            'expired_at' => now()->subDay(),
        ]);
    }
}
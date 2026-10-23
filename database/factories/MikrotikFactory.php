<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Mikrotik;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mikrotik>
 */
class MikrotikFactory extends Factory
{
    protected $model = Mikrotik::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $ip = '192.168.42.' . fake()->unique()->numberBetween(2, 99);

        return [
            'site_name' => fake()->unique()->company() . ' ' . fake()->randomElement(['Kantor', 'Gudang', 'Cabang']),
            'description' => fake()->optional()->sentence(),
            'vpn_ip' => $ip,
            'vpn_username' => 'mt-' . fake()->unique()->slug(1),
            'vpn_password_encrypted' => Mikrotik::encryptPassword(fake()->password(12, 16)),
            'winbox_internal_port' => 8291,
            'webfig_internal_port' => 80,
            'is_online' => fake()->boolean(30),
            'last_seen_at' => fn () => now()->subMinutes(fake()->numberBetween(0, 60)),
        ];
    }

    public function online(): static
    {
        return $this->state(fn (array $a) => [
            'is_online' => true,
            'last_seen_at' => now(),
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn (array $a) => [
            'is_online' => false,
        ]);
    }
}
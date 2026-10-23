<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Mikrotik;
use App\Models\NginxMapping;
use App\Models\User;
use App\Models\VpnUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds panel administrators and the reference branch topology.
 *
 * Deliberately avoids model factories: production installs run
 * `composer install --no-dev`, so fakerphp/faker is not available there.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Reference branch topology from the project specification.
     *
     * @var list<array<string, mixed>>
     */
    private const SITES = [
        ['site_name' => 'Kantor', 'vpn_ip' => '192.168.42.2', 'vpn_username' => 'mt-kantor', 'winbox_public' => 8291, 'webfig_public' => 8080],
        ['site_name' => 'Gudang', 'vpn_ip' => '192.168.42.3', 'vpn_username' => 'mt-gudang', 'winbox_public' => 8292, 'webfig_public' => 8081],
        ['site_name' => 'Cabang A', 'vpn_ip' => '192.168.42.4', 'vpn_username' => 'mt-cabang-a', 'winbox_public' => 8293, 'webfig_public' => 8082],
        ['site_name' => 'Cabang B', 'vpn_ip' => '192.168.42.5', 'vpn_username' => 'mt-cabang-b', 'winbox_public' => 8294, 'webfig_public' => 8083],
    ];

    public function run(): void
    {
        $this->seedUsers();
        $this->seedSites();
    }

    private function seedUsers(): void
    {
        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => 'VPN Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe!')),
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        User::firstOrCreate(
            ['email' => env('OPERATOR_EMAIL', 'operator@example.com')],
            [
                'name' => 'Operator',
                'password' => Hash::make(env('OPERATOR_PASSWORD', 'ChangeMe!')),
                'is_admin' => false,
                'email_verified_at' => now(),
            ],
        );
    }

    private function seedSites(): void
    {
        $vpnPassword = (string) env('SEED_VPN_PASSWORD', 'password123');

        foreach (self::SITES as $site) {
            $mikrotik = Mikrotik::firstOrCreate(
                ['vpn_username' => $site['vpn_username']],
                [
                    'site_name' => $site['site_name'],
                    'description' => 'Seeded reference branch',
                    'vpn_ip' => $site['vpn_ip'],
                    'vpn_password_encrypted' => Mikrotik::encryptPassword($vpnPassword),
                    'winbox_internal_port' => 8291,
                    'webfig_internal_port' => 80,
                    'winbox_public_port' => $site['winbox_public'],
                    'webfig_public_port' => $site['webfig_public'],
                    'routeros_version' => '7.14.3',
                    'is_online' => false,
                ],
            );

            VpnUser::firstOrCreate(
                ['username' => $site['vpn_username']],
                [
                    'password' => $vpnPassword,
                    'assigned_ip' => $site['vpn_ip'],
                    'notes' => 'Seeded for ' . $site['site_name'],
                    'is_enabled' => true,
                ],
            );

            foreach ([
                ['conn_type' => 'winbox', 'public_port' => $site['winbox_public']],
                ['conn_type' => 'webfig', 'public_port' => $site['webfig_public']],
            ] as $mapping) {
                NginxMapping::firstOrCreate(
                    ['mikrotik_id' => $mikrotik->id, 'conn_type' => $mapping['conn_type']],
                    ['public_port' => $mapping['public_port'], 'is_enabled' => true],
                );
            }
        }
    }
}
